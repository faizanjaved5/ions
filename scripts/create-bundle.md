# Channel Bundle Management Tools

This directory contains tools for creating and managing channel bundles in the ION platform, including support for very large bundles with thousands of channels.

## Tools Overview

### 1. Web Interface - Channel Bundle Manager
**File:** `../app/channel-bundle-manager.php`
**URL:** `https://yourdomain.com/app/channel-bundle-manager.php`

A user-friendly web interface for creating and managing channel bundles.

**Features:**
- Create bundles with up to 1,000 channels via web interface
- Visual channel selection with search
- Bulk upload via CSV files
- Edit and delete existing bundles
- Real-time validation

### 2. Web Interface - Bulk Channel Processor
**File:** `../app/bulk-channel-processor.php`
**URL:** `https://yourdomain.com/app/bulk-channel-processor.php`

Specialized interface for very large bundles (thousands of channels).

**Features:**
- Handle bundles with 10,000+ channels
- Multiple input formats (CSV, TXT, JSON)
- Chunked processing to avoid timeouts
- Real-time validation and progress tracking
- Memory-efficient processing

### 3. Command Line Interface
**File:** `create-bundle-cli.php`

CLI tool for batch operations and automation.

**Usage:**
```bash
php create-bundle-cli.php --name="Bundle Name" --slug="bundle-slug" --file="channels.csv" --price=299.99
```

**Options:**
- `--name="Bundle Name"` - Bundle name (required)
- `--slug="bundle-slug"` - Bundle slug (required)
- `--file="channels.csv"` - CSV file with channel slugs (required)
- `--price=299.99` - Bundle price (optional)
- `--description="Description"` - Bundle description (optional)
- `--dry-run` - Validate only, don't create (optional)
- `--help` - Show help message

## File Formats

### CSV Format
```csv
# Comments start with #
ion-new-york,New York City
ion-los-angeles,Los Angeles
ion-chicago,Chicago
```

### TXT Format
```
ion-new-york
ion-los-angeles
ion-chicago
```

### JSON Format
```json
["ion-new-york", "ion-los-angeles", "ion-chicago"]
```

## Performance Considerations

### Small Bundles (1-100 channels)
- Use the **Channel Bundle Manager** web interface
- Real-time validation and selection
- Immediate feedback

### Medium Bundles (100-1,000 channels)
- Use the **Channel Bundle Manager** with bulk upload
- CSV file upload recommended
- Progress tracking available

### Large Bundles (1,000-10,000 channels)
- Use the **Bulk Channel Processor** web interface
- Chunked processing (500-1000 channels per chunk)
- Progress bars and validation
- Memory-efficient processing

### Very Large Bundles (10,000+ channels)
- Use the **CLI tool** for best performance
- Batch processing with validation
- No web timeout issues
- Automated error handling

## Database Schema

The tools work with the `IONLocalBundles` table:

```sql
CREATE TABLE IONLocalBundles (
    id INT(11) NOT NULL AUTO_INCREMENT,
    bundle_name VARCHAR(255) NOT NULL,
    bundle_slug VARCHAR(255) NOT NULL UNIQUE,
    description TEXT NULL,
    price DECIMAL(10,2) DEFAULT 0.00,
    currency VARCHAR(3) DEFAULT 'USD',
    channel_count INT(11) DEFAULT 0,
    channels JSON NOT NULL,           -- Array of channel slugs
    categories JSON NULL,             -- Supported categories
    status ENUM('active', 'inactive', 'draft') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by VARCHAR(50) NULL,
    PRIMARY KEY (id)
);
```

## Channel Validation

All tools validate channel slugs against the `IONLocalNetwork` table to ensure:
- Channels exist in the database
- Invalid channels are reported
- Only valid channels are included in bundles

## Error Handling

### Common Issues
1. **Invalid channel slugs** - Check against `IONLocalNetwork` table
2. **Duplicate bundle slugs** - Use unique slugs
3. **File format errors** - Ensure proper CSV/JSON format
4. **Memory issues** - Use CLI tool for very large bundles
5. **Timeout issues** - Use chunked processing

### Troubleshooting
1. Check database connection
2. Verify channel slugs exist in `IONLocalNetwork`
3. Ensure proper file permissions
4. Check PHP memory limits for large bundles
5. Use `--dry-run` flag to validate before creating

## Examples

### Create a Major Cities Bundle
```bash
php create-bundle-cli.php \
  --name="Major Cities Bundle" \
  --slug="major-cities" \
  --file="sample-channels.csv" \
  --price=299.99 \
  --description="Top 50 major US cities"
```

### Create a Sports Network Bundle
```bash
php create-bundle-cli.php \
  --name="Sports Network" \
  --slug="sports-network" \
  --file="sports-channels.csv" \
  --price=199.99 \
  --description="Sports-focused channels"
```

### Validate Channels Only
```bash
php create-bundle-cli.php \
  --name="Test Bundle" \
  --slug="test-bundle" \
  --file="channels.csv" \
  --dry-run
```

## Security Notes

- All tools require Admin or Owner access
- Input validation on all data
- SQL injection protection via prepared statements
- File upload restrictions
- CSRF protection on web interfaces

## Support

For issues or questions:
1. Check the error messages in the tools
2. Verify database connectivity
3. Check file permissions
4. Review the logs for detailed error information
