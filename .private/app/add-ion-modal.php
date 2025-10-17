<!-- ION Channel Wizard Modal -->
<div id="add-ion-modal" class="wizard-overlay" data-mode="add" style="display: none;">
    <div class="wizard-container">
        <div class="wizard-card">
            <div class="wizard-header">
                <div class="wizard-header-content">
                    <div class="wizard-header-left">
                        <div class="wizard-icon">
                            <span style="font-size: 1.25rem;">+</span>
                        </div>
                        <div>
                            <h2 id="modal-title" class="wizard-title">Add New ION Channel</h2>
                            <p id="modal-description" class="wizard-description">Step 1 of 5: Basics</p>
                        </div>
                    </div>
                    <div class="wizard-header-right">
                        <button type="button" id="theme-toggle" class="btn btn-icon btn-ghost" aria-label="Toggle theme">
                            <span class="theme-icon light-icon">🌙</span>
                            <span class="theme-icon dark-icon">☀️</span>
                        </button>
                        <button type="button" id="close-modal" class="btn btn-icon btn-ghost" aria-label="Close modal">×</button>
                    </div>
                </div>
            </div>
            <div class="wizard-content">
                <form id="add-ion-form" class="ion-form">
                    <!-- Step 1: Basics -->
                    <div id="step-1" class="wizard-step active">
                        <div class="step-header">
                            <h3 class="step-title">Basic Information</h3>
                            <p class="step-description">Enter the essential details for your ION channel</p>
                        </div>
                        
                        <div class="form-layout">
                            <div class="form-row">
                                <div class="form-field">
                                    <label for="city_name" class="field-label">
                                        City/Town Name <span class="required">*</span>
                                        <span class="char-count" id="city_name_count">(0)</span>
                                    </label>
                                    <div class="input-group">
                                        <input type="text" id="city_name" name="city_name" class="field-input" 
                                               placeholder="Enter city or town name" required>
                                        <div class="input-validation" id="city_name_validation"></div>
                                    </div>
                                </div>
                                
                                <div class="form-field">
                                    <label for="status" class="field-label">Status</label>
                                    <div class="input-group">
                                        <select id="status" name="status" class="field-select">
                                            <option value="Draft">Draft Page</option>
                                            <option value="Live">Live Channel</option>
                                            <option value="Preview">Preview Page</option>
                                            <option value="Static">Static (WP)</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-field">
                                    <label for="channel_name" class="field-label">
                                        ION Channel Name <span class="required">*</span>
                                        <span class="char-count" id="channel_name_count">(0)</span>
                                    </label>
                                    <div class="input-group">
                                        <input type="text" id="channel_name" name="channel_name" class="field-input" 
                                               placeholder="Enter channel name">
                                        <div class="input-validation" id="channel_name_validation"></div>
                                    </div>
                                </div>
                                
                                <div class="form-field">
                                    <label for="country" class="field-label">
                                        Country <span class="required">*</span>
                                    </label>
                                    <div class="input-group">
                                        <select id="country" name="country" class="field-select" required>
                                            <option value="US" selected>United States (US)</option>
                                            <option value="CA">Canada (CA)</option>
                                            <option value="GB">United Kingdom (GB)</option>
                                            <option value="AU">Australia (AU)</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-field">
                                    <label for="custom_domain" class="field-label">
                                        ION Domain Name <span class="required">*</span>
                                        <span class="char-count" id="custom_domain_count">(0)</span>
                                    </label>
                                    <div class="input-group">
                                        <input type="text" id="custom_domain" name="custom_domain" class="field-input" 
                                               placeholder="Enter domain name">
                                        <div class="input-validation" id="custom_domain_validation"></div>
                                    </div>
                                    <p class="field-hint">
                                        Enter just the domain (e.g., example.com). We'll automatically remove https://, http://, and www. prefixes.
                                    </p>
                                </div>
                                
                                <div class="form-field">
                                    <label for="state" class="field-label">State or Province</label>
                                    <div class="input-group">
                                        <select id="state" name="state" class="field-select">
                                            <option value="">All States/Provinces</option>
                                            <optgroup label="United States">
                                                <option value="AL">Alabama (AL)</option>
                                                <option value="AK">Alaska (AK)</option>
                                                <option value="AZ">Arizona (AZ)</option>
                                                <option value="AR">Arkansas (AR)</option>
                                                <option value="CA">California (CA)</option>
                                                <option value="CO">Colorado (CO)</option>
                                                <option value="CT">Connecticut (CT)</option>
                                                <option value="DE">Delaware (DE)</option>
                                                <option value="FL">Florida (FL)</option>
                                                <option value="GA">Georgia (GA)</option>
                                                <option value="HI">Hawaii (HI)</option>
                                                <option value="ID">Idaho (ID)</option>
                                                <option value="IL">Illinois (IL)</option>
                                                <option value="IN">Indiana (IN)</option>
                                                <option value="IA">Iowa (IA)</option>
                                                <option value="KS">Kansas (KS)</option>
                                                <option value="KY">Kentucky (KY)</option>
                                                <option value="LA">Louisiana (LA)</option>
                                                <option value="ME">Maine (ME)</option>
                                                <option value="MD">Maryland (MD)</option>
                                                <option value="MA">Massachusetts (MA)</option>
                                                <option value="MI">Michigan (MI)</option>
                                                <option value="MN">Minnesota (MN)</option>
                                                <option value="MS">Mississippi (MS)</option>
                                                <option value="MO">Missouri (MO)</option>
                                                <option value="MT">Montana (MT)</option>
                                                <option value="NE">Nebraska (NE)</option>
                                                <option value="NV">Nevada (NV)</option>
                                                <option value="NH">New Hampshire (NH)</option>
                                                <option value="NJ">New Jersey (NJ)</option>
                                                <option value="NM">New Mexico (NM)</option>
                                                <option value="NY">New York (NY)</option>
                                                <option value="NC">North Carolina (NC)</option>
                                                <option value="ND">North Dakota (ND)</option>
                                                <option value="OH">Ohio (OH)</option>
                                                <option value="OK">Oklahoma (OK)</option>
                                                <option value="OR">Oregon (OR)</option>
                                                <option value="PA">Pennsylvania (PA)</option>
                                                <option value="RI">Rhode Island (RI)</option>
                                                <option value="SC">South Carolina (SC)</option>
                                                <option value="SD">South Dakota (SD)</option>
                                                <option value="TN">Tennessee (TN)</option>
                                                <option value="TX">Texas (TX)</option>
                                                <option value="UT">Utah (UT)</option>
                                                <option value="VT">Vermont (VT)</option>
                                                <option value="VA">Virginia (VA)</option>
                                                <option value="WA">Washington (WA)</option>
                                                <option value="WV">West Virginia (WV)</option>
                                                <option value="WI">Wisconsin (WI)</option>
                                                <option value="WY">Wyoming (WY)</option>
                                            </optgroup>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Step 2: Media -->
                    <div id="step-2" class="wizard-step hidden">
                        <h3 class="text-lg font-weight-600 mb-4">Media & Assets</h3>
                        <p class="text-muted-foreground mb-4">Upload images and videos for your channel</p>
                        
                        <div class="form-group">
                            <label class="form-label">Header Image:</label>
                            <input type="url" id="header_image_url" class="form-input" placeholder="https://example.com/image.jpg">
                            <button type="button" class="btn btn-ghost mt-2">📤 Upload</button>
                        </div>
                        
                        <div class="form-group">
                            <div class="upload-area" id="media-upload-area">
                                <div class="text-center">
                                    <div style="font-size: 3rem; margin-bottom: 1rem;">📤</div>
                                    <h4>Drop files here or click to upload</h4>
                                    <p class="upload-text">Upload multiple images and videos for your channel</p>
                                </div>
                                <input type="file" id="media-files" multiple accept="image/*,video/*,.mp4,.mov,.avi,.wmv" style="display: none;">
                                <button type="button" class="btn btn-default" onclick="document.getElementById('media-files').click()">Choose Files</button>
                            </div>
                            <p class="text-sm text-muted-foreground mt-2">
                                Images: JPG, PNG, GIF up to 5MB each<br>
                                Videos: MP4, WebM up to 100MB each
                            </p>
                        </div>
                        
                        <div class="form-group">
                            <h4 class="text-base font-weight-500 mb-2">Uploaded Files</h4>
                            <div id="uploaded-files-list" class="text-sm text-muted-foreground">
                                No files uploaded yet
                            </div>
                        </div>
                        
                        <!-- Hidden field to store comma-separated media URLs -->
                        <input type="hidden" id="image_path" name="image_path" value="">
                    </div>
                    
                    <!-- Step 3: SEO -->
                    <div id="step-3" class="wizard-step hidden">
                        <h3 class="text-lg font-weight-600 mb-4">Channel Details</h3>
                        <p class="text-muted-foreground mb-4">Configure your channel's public appearance and SEO</p>
                        
                        <div class="form-group">
                            <label for="title" class="form-label">Channel Title (Public):</label>
                            <input type="text" id="title" name="title" class="form-input" 
                                   placeholder="Enter public channel title">
                        </div>
                        
                        <div class="form-group">
                            <label for="description" class="form-label">Channel Subtitle (Public):</label>
                            <textarea id="description" name="description" class="form-textarea" 
                                      placeholder="Enter channel subtitle or description" rows="3"></textarea>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div class="form-group">
                                <label for="seo_title" class="form-label">SEO Title:</label>
                                <input type="text" id="seo_title" name="seo_title" class="form-input" 
                                       placeholder="Enter SEO optimized title">
                            </div>
                            
                            <div class="form-group">
                                <label for="seo_meta_description" class="form-label">SEO Meta Description:</label>
                                <textarea id="seo_meta_description" name="seo_meta_description" class="form-textarea" 
                                          placeholder="Enter SEO meta description" rows="3"></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Step 4: Geo -->
                    <div id="step-4" class="wizard-step hidden">
                        <h3 class="text-lg font-weight-600 mb-4">Geographic Data</h3>
                        <p class="text-muted-foreground mb-4">Set location and population information</p>
                        
                        <div class="grid grid-cols-3 gap-4">
                            <div class="form-group">
                                <label for="latitude" class="form-label">Latitude:</label>
                                <input type="text" id="latitude" name="latitude" class="form-input" 
                                       placeholder="40.7128">
                            </div>
                            
                            <div class="form-group">
                                <label for="longitude" class="form-label">Longitude:</label>
                                <input type="text" id="longitude" name="longitude" class="form-input" 
                                       placeholder="-74.0060">
                            </div>
                            
                            <div class="form-group">
                                <label for="population" class="form-label">👥 Population:</label>
                                <input type="text" id="population" name="population" class="form-input" 
                                       placeholder="1000000">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <div class="bg-muted p-4 rounded border" style="text-align: center;">
                                <h4 class="text-base font-weight-500 mb-2">Auto-fill Geographic Data</h4>
                                <p class="text-muted-foreground mb-4">
                                    Automatically fetch coordinates and population data based on your city/town name
                                </p>
                                <button type="button" id="fetch-geo-btn" class="btn btn-default">
                                    <span class="btn-icon">✅</span>
                                    <span class="btn-text">Fetch Geo Data</span>
                                </button>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <div class="bg-muted p-4 rounded" style="text-align: center; min-height: 200px; display: flex; align-items: center; justify-content: center; flex-direction: column;">
                                <div style="font-size: 3rem; margin-bottom: 1rem;">🗺️</div>
                                <h4 class="text-base font-weight-500 mb-2">Location Preview</h4>
                                <p class="text-muted-foreground">Enter coordinates to see location preview</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Step 5: Preview -->
                    <div id="step-5" class="wizard-step hidden">
                        <h3 class="text-lg font-weight-600 mb-4">Preview Your ION Channel</h3>
                        <p class="text-muted-foreground mb-4">Review all details before creating your channel</p>
                        
                        <div class="bg-card border rounded p-4">
                            <div class="form-group">
                                <h4 class="text-base font-weight-600 mb-2">👁️ Basic Information</h4>
                                <div class="grid grid-cols-2 gap-4 text-sm">
                                    <div>
                                        <strong>City/Town Name</strong><br>
                                        <span id="preview-city-name" class="text-muted-foreground">📍 Not specified</span>
                                    </div>
                                    <div>
                                        <strong>Status</strong><br>
                                        <span id="preview-status" class="text-muted-foreground">Draft Page</span>
                                    </div>
                                    <div>
                                        <strong>Channel Name</strong><br>
                                        <span id="preview-channel-name" class="text-muted-foreground">Not specified</span>
                                    </div>
                                    <div>
                                        <strong>Domain Name</strong><br>
                                        <span id="preview-domain-name" class="text-muted-foreground">🌐 Not specified</span>
                                    </div>
                                    <div>
                                        <strong>Country</strong><br>
                                        <span id="preview-country" class="text-muted-foreground">United States of America</span>
                                    </div>
                                    <div>
                                        <strong>State/Province</strong><br>
                                        <span id="preview-state" class="text-muted-foreground">All States/Provinces</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <h4 class="text-base font-weight-600 mb-2">Channel Details</h4>
                                <div class="grid grid-cols-2 gap-4 text-sm">
                                    <div>
                                        <strong>Channel Title</strong><br>
                                        <span id="preview-title" class="text-muted-foreground">Not specified</span>
                                    </div>
                                    <div>
                                        <strong>Channel Subtitle</strong><br>
                                        <span id="preview-description" class="text-muted-foreground">Not specified</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="wizard-footer">
                <div class="wizard-footer-content">
                    <div class="step-navigation">
                        <div class="step-item">
                            <button type="button" class="step-button active" data-step="1">Basics</button>
                        </div>
                        <div class="step-item">
                            <button type="button" class="step-button inactive" data-step="2">Media</button>
                        </div>
                        <div class="step-item">
                            <button type="button" class="step-button inactive" data-step="3">SEO</button>
                        </div>
                        <div class="step-item">
                            <button type="button" class="step-button inactive" data-step="4">Geo</button>
                        </div>
                        <div class="step-item">
                            <button type="button" class="step-button inactive last" data-step="5">Preview</button>
                        </div>
                    </div>
                    
                    <div class="footer-action">
                        <button type="button" id="create-ion-btn" class="btn btn-action">
                            <span class="btn-icon">✨</span>
                            <span id="create-btn-text">Create ION Channel</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Success Modal -->
<div id="success-modal" class="wizard-overlay" style="display: none;">
    <div class="wizard-container" style="max-width: 400px; height: auto;">
        <div class="wizard-card">
            <div class="wizard-content text-center p-8">
                <div class="wizard-icon" style="margin: 0 auto 1rem; background: linear-gradient(135deg, #22c55e, #16a34a);">
                    <span style="font-size: 1.5rem;">🎉</span>
                </div>
                <h2 class="text-2xl font-weight-600 mb-2">Congratulations!</h2>
                <h3 class="text-xl text-primary mb-4">New ION Channel<br>Has Been Added</h3>
                <p class="text-muted-foreground mb-6">Your new ION channel has been successfully created and is ready to go live!</p>
                <div class="flex gap-2 justify-center">
                    <button type="button" id="close-success-btn" class="btn btn-ghost">Close</button>
                    <button type="button" id="view-channel-btn" class="btn btn-action">View Channel</button>
                </div>
            </div>
        </div>
    </div>
</div> 