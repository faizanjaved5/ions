/**
 * Cloudflare Worker: Google Drive to R2 Direct Stream Transfer
 * Handles large video files with multipart upload
 */

export default {
  async fetch(request, env, ctx) {
    // Only allow POST requests
    if (request.method !== 'POST') {
      return new Response('Method not allowed', { status: 405 });
    }

    try {
      const requestData = await request.json();
      const { 
        fileId, 
        accessToken, 
        fileName, 
        fileSize, 
        mimeType,
        bucketName,
        accountId,
        accessKeyId,
        secretAccessKey,
        region = 'auto'
      } = requestData;

      // Validate required parameters
      if (!fileId || !accessToken || !fileName || !bucketName) {
        return new Response(JSON.stringify({
          success: false,
          error: 'Missing required parameters'
        }), { 
          status: 400,
          headers: { 'Content-Type': 'application/json' }
        });
      }

      console.log(`Starting transfer: ${fileName} (${fileSize} bytes) from Google Drive to R2`);

      // For files larger than 100MB, use multipart upload
      const useMultipart = fileSize > (100 * 1024 * 1024);

      let result;
      if (useMultipart) {
        result = await handleMultipartUpload(
          fileId, accessToken, fileName, fileSize, mimeType,
          bucketName, accountId, accessKeyId, secretAccessKey, region
        );
      } else {
        result = await handleSingleUpload(
          fileId, accessToken, fileName, mimeType,
          bucketName, accountId, accessKeyId, secretAccessKey, region
        );
      }

      return new Response(JSON.stringify(result), {
        headers: { 'Content-Type': 'application/json' }
      });

    } catch (error) {
      console.error('Worker error:', error);
      return new Response(JSON.stringify({
        success: false,
        error: error.message
      }), { 
        status: 500,
        headers: { 'Content-Type': 'application/json' }
      });
    }
  }
};

/**
 * Handle single upload for smaller files (< 100MB)
 */
async function handleSingleUpload(fileId, accessToken, fileName, mimeType, bucketName, accountId, accessKeyId, secretAccessKey, region) {
  try {
    // Fetch file from Google Drive
    console.log('Fetching file from Google Drive...');
    const driveResponse = await fetch(`https://www.googleapis.com/drive/v3/files/${fileId}?alt=media`, {
      headers: {
        'Authorization': `Bearer ${accessToken}`
      }
    });

    if (!driveResponse.ok) {
      throw new Error(`Google Drive API error: ${driveResponse.status} ${driveResponse.statusText}`);
    }

    // Generate unique key for R2
    const timestamp = new Date().toISOString().slice(0, 10).replace(/-/g, '/');
    const uniqueId = crypto.randomUUID();
    const key = `${timestamp}/${uniqueId}_${fileName}`;

    // Create AWS signature for R2
    const { url, headers } = await createR2SignedRequest(
      'PUT', bucketName, key, accountId, accessKeyId, secretAccessKey, region, mimeType
    );

    // Stream directly from Google Drive to R2
    console.log('Streaming to R2...');
    const r2Response = await fetch(url, {
      method: 'PUT',
      headers: {
        ...headers,
        'Content-Type': mimeType
      },
      body: driveResponse.body
    });

    if (!r2Response.ok) {
      throw new Error(`R2 upload error: ${r2Response.status} ${r2Response.statusText}`);
    }

    const publicUrl = `https://${bucketName}.${accountId}.r2.cloudflarestorage.com/${key}`;

    return {
      success: true,
      fileName: key,
      url: publicUrl,
      method: 'single_upload'
    };

  } catch (error) {
    console.error('Single upload error:', error);
    return {
      success: false,
      error: error.message
    };
  }
}

/**
 * Handle multipart upload for larger files (> 100MB)
 */
async function handleMultipartUpload(fileId, accessToken, fileName, fileSize, mimeType, bucketName, accountId, accessKeyId, secretAccessKey, region) {
  try {
    // Generate unique key for R2
    const timestamp = new Date().toISOString().slice(0, 10).replace(/-/g, '/');
    const uniqueId = crypto.randomUUID();
    const key = `${timestamp}/${uniqueId}_${fileName}`;

    console.log('Initiating multipart upload...');

    // Step 1: Initiate multipart upload
    const initiateResult = await initiateMultipartUpload(bucketName, key, accountId, accessKeyId, secretAccessKey, region, mimeType);
    if (!initiateResult.success) {
      throw new Error(`Failed to initiate multipart upload: ${initiateResult.error}`);
    }

    const uploadId = initiateResult.uploadId;
    console.log(`Multipart upload initiated: ${uploadId}`);

    // Step 2: Upload parts in chunks
    const chunkSize = 50 * 1024 * 1024; // 50MB chunks
    const parts = [];
    let partNumber = 1;
    let uploadedBytes = 0;

    // Get file stream from Google Drive
    const driveResponse = await fetch(`https://www.googleapis.com/drive/v3/files/${fileId}?alt=media`, {
      headers: {
        'Authorization': `Bearer ${accessToken}`
      }
    });

    if (!driveResponse.ok) {
      throw new Error(`Google Drive API error: ${driveResponse.status} ${driveResponse.statusText}`);
    }

    const reader = driveResponse.body.getReader();
    let buffer = new Uint8Array(0);

    while (true) {
      const { done, value } = await reader.read();
      
      if (value) {
        // Append new data to buffer
        const newBuffer = new Uint8Array(buffer.length + value.length);
        newBuffer.set(buffer);
        newBuffer.set(value, buffer.length);
        buffer = newBuffer;
      }

      // Upload chunk if buffer is large enough or if we're done
      if (buffer.length >= chunkSize || (done && buffer.length > 0)) {
        const chunkData = buffer.slice(0, Math.min(chunkSize, buffer.length));
        
        console.log(`Uploading part ${partNumber}, size: ${chunkData.length} bytes`);
        
        const partResult = await uploadPart(
          bucketName, key, uploadId, partNumber, chunkData,
          accountId, accessKeyId, secretAccessKey, region
        );

        if (!partResult.success) {
          throw new Error(`Failed to upload part ${partNumber}: ${partResult.error}`);
        }

        parts.push({
          PartNumber: partNumber,
          ETag: partResult.etag
        });

        uploadedBytes += chunkData.length;
        partNumber++;

        // Update buffer with remaining data
        buffer = buffer.slice(chunkData.length);
      }

      if (done && buffer.length === 0) {
        break;
      }
    }

    console.log(`Uploaded ${parts.length} parts, total: ${uploadedBytes} bytes`);

    // Step 3: Complete multipart upload
    const completeResult = await completeMultipartUpload(
      bucketName, key, uploadId, parts,
      accountId, accessKeyId, secretAccessKey, region
    );

    if (!completeResult.success) {
      throw new Error(`Failed to complete multipart upload: ${completeResult.error}`);
    }

    const publicUrl = `https://${bucketName}.${accountId}.r2.cloudflarestorage.com/${key}`;

    return {
      success: true,
      fileName: key,
      url: publicUrl,
      method: 'multipart_upload',
      parts: parts.length,
      totalBytes: uploadedBytes
    };

  } catch (error) {
    console.error('Multipart upload error:', error);
    return {
      success: false,
      error: error.message
    };
  }
}

/**
 * Initiate multipart upload
 */
async function initiateMultipartUpload(bucketName, key, accountId, accessKeyId, secretAccessKey, region, mimeType) {
  try {
    const { url, headers } = await createR2SignedRequest(
      'POST', bucketName, key + '?uploads', accountId, accessKeyId, secretAccessKey, region, mimeType
    );

    const response = await fetch(url, {
      method: 'POST',
      headers: {
        ...headers,
        'Content-Type': mimeType
      }
    });

    if (!response.ok) {
      throw new Error(`HTTP ${response.status}: ${response.statusText}`);
    }

    const responseText = await response.text();
    const uploadIdMatch = responseText.match(/<UploadId>([^<]+)<\/UploadId>/);
    
    if (!uploadIdMatch) {
      throw new Error('Could not extract upload ID from response');
    }

    return {
      success: true,
      uploadId: uploadIdMatch[1]
    };

  } catch (error) {
    return {
      success: false,
      error: error.message
    };
  }
}

/**
 * Upload a single part
 */
async function uploadPart(bucketName, key, uploadId, partNumber, data, accountId, accessKeyId, secretAccessKey, region) {
  try {
    const { url, headers } = await createR2SignedRequest(
      'PUT', bucketName, `${key}?partNumber=${partNumber}&uploadId=${uploadId}`,
      accountId, accessKeyId, secretAccessKey, region
    );

    const response = await fetch(url, {
      method: 'PUT',
      headers,
      body: data
    });

    if (!response.ok) {
      throw new Error(`HTTP ${response.status}: ${response.statusText}`);
    }

    const etag = response.headers.get('ETag');
    if (!etag) {
      throw new Error('No ETag received from R2');
    }

    return {
      success: true,
      etag: etag.replace(/"/g, '') // Remove quotes from ETag
    };

  } catch (error) {
    return {
      success: false,
      error: error.message
    };
  }
}

/**
 * Complete multipart upload
 */
async function completeMultipartUpload(bucketName, key, uploadId, parts, accountId, accessKeyId, secretAccessKey, region) {
  try {
    const { url, headers } = await createR2SignedRequest(
      'POST', bucketName, `${key}?uploadId=${uploadId}`,
      accountId, accessKeyId, secretAccessKey, region
    );

    const completeXML = `<CompleteMultipartUpload>
      ${parts.map(part => `<Part><PartNumber>${part.PartNumber}</PartNumber><ETag>"${part.ETag}"</ETag></Part>`).join('')}
    </CompleteMultipartUpload>`;

    const response = await fetch(url, {
      method: 'POST',
      headers: {
        ...headers,
        'Content-Type': 'application/xml'
      },
      body: completeXML
    });

    if (!response.ok) {
      throw new Error(`HTTP ${response.status}: ${response.statusText}`);
    }

    return { success: true };

  } catch (error) {
    return {
      success: false,
      error: error.message
    };
  }
}

/**
 * Create AWS v4 signed request for R2
 */
async function createR2SignedRequest(method, bucketName, key, accountId, accessKeyId, secretAccessKey, region, contentType = 'application/octet-stream') {
  const host = `${accountId}.r2.cloudflarestorage.com`;
  const endpoint = `https://${host}`;
  const url = `${endpoint}/${bucketName}/${key}`;
  
  const now = new Date();
  const dateStamp = now.toISOString().slice(0, 10).replace(/-/g, '');
  const amzDate = now.toISOString().replace(/[:-]|\.\d{3}/g, '');

  // Create canonical request
  const canonicalHeaders = `host:${host}\nx-amz-content-sha256:UNSIGNED-PAYLOAD\nx-amz-date:${amzDate}\n`;
  const signedHeaders = 'host;x-amz-content-sha256;x-amz-date';
  const canonicalRequest = `${method}\n/${bucketName}/${key}\n\n${canonicalHeaders}\n${signedHeaders}\nUNSIGNED-PAYLOAD`;

  // Create string to sign
  const algorithm = 'AWS4-HMAC-SHA256';
  const credentialScope = `${dateStamp}/${region}/s3/aws4_request`;
  const stringToSign = `${algorithm}\n${amzDate}\n${credentialScope}\n${await sha256(canonicalRequest)}`;

  // Calculate signature
  const kDate = await hmacSha256(dateStamp, `AWS4${secretAccessKey}`);
  const kRegion = await hmacSha256(region, kDate);
  const kService = await hmacSha256('s3', kRegion);
  const kSigning = await hmacSha256('aws4_request', kService);
  const signature = await hmacSha256(stringToSign, kSigning, 'hex');

  // Create authorization header
  const authorization = `${algorithm} Credential=${accessKeyId}/${credentialScope}, SignedHeaders=${signedHeaders}, Signature=${signature}`;

  return {
    url,
    headers: {
      'Authorization': authorization,
      'x-amz-content-sha256': 'UNSIGNED-PAYLOAD',
      'x-amz-date': amzDate
    }
  };
}

/**
 * Helper functions for AWS signature
 */
async function sha256(message) {
  const encoder = new TextEncoder();
  const data = encoder.encode(message);
  const hashBuffer = await crypto.subtle.digest('SHA-256', data);
  return Array.from(new Uint8Array(hashBuffer))
    .map(b => b.toString(16).padStart(2, '0'))
    .join('');
}

async function hmacSha256(message, key, format = 'buffer') {
  const encoder = new TextEncoder();
  
  let cryptoKey;
  if (typeof key === 'string') {
    const keyData = encoder.encode(key);
    cryptoKey = await crypto.subtle.importKey(
      'raw', keyData, { name: 'HMAC', hash: 'SHA-256' }, false, ['sign']
    );
  } else {
    cryptoKey = await crypto.subtle.importKey(
      'raw', key, { name: 'HMAC', hash: 'SHA-256' }, false, ['sign']
    );
  }

  const messageData = encoder.encode(message);
  const signature = await crypto.subtle.sign('HMAC', cryptoKey, messageData);

  if (format === 'hex') {
    return Array.from(new Uint8Array(signature))
      .map(b => b.toString(16).padStart(2, '0'))
      .join('');
  }

  return signature;
}