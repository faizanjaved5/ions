<?php
// mobile_filters.php

global $view, $sort, $status_filter, $country_filter, $state_filter, $search, $countries, $wpdb, $table;

?>
<form action="" method="get" id="mobile-filter-form">
    <input type="hidden" name="view" value="<?= h($view) ?>">
    <input type="hidden" name="q" value="<?= h($search) ?>">

    <label for="mobile-sort">Sort by:</label>
    <select name="sort" id="mobile-sort" onchange="this.form.submit()">
        <option value="city_name" <?= $sort === 'city_name' ? 'selected' : '' ?>>City</option>
        <option value="state_name" <?= $sort === 'state_name' ? 'selected' : '' ?>>State</option>
        <option value="country_name" <?= $sort === 'country_name' ? 'selected' : '' ?>>Country</option>
        <option value="population" <?= $sort === 'population' ? 'selected' : '' ?>>Population</option>
        <option value="custom_domain" <?= $sort === 'custom_domain' ? 'selected' : '' ?>>Domain</option>
        <option value="status" <?= $sort === 'status' ? 'selected' : '' ?>>Status</option>
    </select>

    <label for="mobile-status">Status:</label>
    <select name="status" id="mobile-status" onchange="this.form.submit()">
        <option value="">All Status</option>
        <option value="live" <?= $status_filter === 'live' ? 'selected' : '' ?>>Live Channels (<?= number_format($status_counts['live'] ?? 0) ?>)</option>
        <option value="preview" <?= $status_filter === 'preview' ? 'selected' : '' ?>>Preview Pages (<?= number_format($status_counts['preview'] ?? 0) ?>)</option>
        <option value="static" <?= $status_filter === 'static' ? 'selected' : '' ?>>Static WP (<?= number_format($status_counts['static'] ?? 0) ?>)</option>
        <option value="draft" <?= $status_filter === 'draft' ? 'selected' : '' ?>>Draft Pages (<?= number_format($status_counts['draft'] ?? 0) ?>)</option>
        <option value="error" <?= $status_filter === 'error' ? 'selected' : '' ?>>Has errors (<?= number_format($status_counts['error'] ?? 0) ?>)</option>
        <option value="cf-active" <?= $status_filter === 'cf-active' ? 'selected' : '' ?>>Cloudflare Active (<?= number_format($status_counts['cf-active'] ?? 0) ?>)</option>
        <option value="cf-missing" <?= $status_filter === 'cf-missing' ? 'selected' : '' ?>>Cloudflare Missing (<?= number_format($status_counts['cf-missing'] ?? 0) ?>)</option>
        <option value="cf-inactive" <?= $status_filter === 'cf-inactive' ? 'selected' : '' ?>>Cloudflare Inactive (<?= number_format($status_counts['cf-inactive'] ?? 0) ?>)</option>
        <option value="cf-pending" <?= $status_filter === 'cf-pending' ? 'selected' : '' ?>>Cloudflare Pending (<?= number_format($status_counts['cf-pending'] ?? 0) ?>)</option>
        <option value="domain-linked" <?= $status_filter === 'domain-linked' ? 'selected' : '' ?>>Domain Linked (<?= number_format($status_counts['domain-linked'] ?? 0) ?>)</option>
        <option value="domain-missing" <?= $status_filter === 'domain-missing' ? 'selected' : '' ?>>Domain Missing (<?= number_format($status_counts['domain-missing'] ?? 0) ?>)</option>
    </select>

    <label for="mobile-country-filter">Country:</label>
    <select name="country" id="mobile-country-filter" onchange="updateMobileStates(this.value); this.form.submit()">
        <option value="US" <?= $country_filter === 'US' ? 'selected' : '' ?>>🇺🇸 United States (US)</option>
        <?php 
        // EMERGENCY FIX: If countries is empty, reload it directly here
        if (!isset($countries) || !is_array($countries) || count($countries) === 0) {
            error_log('MOBILE DEBUG: Countries array is empty, attempting direct reload...');
            try {
                $countries_file = __DIR__ . '/countries.php';
                if (file_exists($countries_file)) {
                    $countries = require $countries_file;
                    error_log('MOBILE DEBUG: Emergency reload successful. Count: ' . count($countries));
                    
                    // Process countries like before
                    $us_country = null;
                    $other_countries = [];
                    foreach ($countries as $c) {
                        if (!is_array($c) || !isset($c['code'], $c['name'])) continue;
                        if ($c['code'] === 'US') {
                            $us_country = $c;
                        } else {
                            $other_countries[] = $c;
                        }
                    }
                    if ($us_country) {
                        array_unshift($other_countries, $us_country);
                        $countries = $other_countries;
                    } else {
                        $countries = $other_countries;
                    }
                } else {
                    error_log('MOBILE DEBUG: countries.php file not found!');
                }
            } catch (Exception $e) {
                error_log('MOBILE DEBUG: Error loading countries: ' . $e->getMessage());
            }
        }
        
        if (isset($countries) && is_array($countries) && count($countries) > 0) {
            foreach ($countries as $c): 
                if (!is_array($c) || !isset($c['code'], $c['name'])) continue; ?>
                <option value="<?= h($c['code']) ?>" <?= $country_filter === $c['code'] ? 'selected' : '' ?>><?= get_flag_emoji($c['code']) ?> <?= h($c['name']) ?></option>
            <?php endforeach;
        } else {
            // Add test countries if still no data
            ?>
            <option value="US"><?= get_flag_emoji('US') ?> United States (TEST)</option>
            <option value="CA"><?= get_flag_emoji('CA') ?> Canada (TEST)</option>
            <?php
        }
        ?>
    </select>

    <label for="mobile-state-filter">State/Province:</label>
    <select name="state" id="mobile-state-filter" onchange="this.form.submit()" <?= !$country_filter ? 'disabled' : '' ?>>
        <option value="">All</option>
    </select>
    
    <hr>
    <h3>View</h3>
    <div class="toggle-buttons">
        <a href="?view=grid&<?= http_build_query(array_diff_key($_GET, ['view' => ''])) ?>" class="<?= $view === 'grid' ? 'active' : '' ?>">Grid</a>
        <a href="?view=list&<?= http_build_query(array_diff_key($_GET, ['view' => ''])) ?>" class="<?= $view === 'list' ? 'active' : '' ?>">List</a>
        <a href="?view=map&<?= http_build_query(array_diff_key($_GET, ['view' => ''])) ?>" class="<?= $view === 'map' ? 'active' : '' ?>">Map</a>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const mobileCountryFilter = document.getElementById('mobile-country-filter');
    const mobileStateFilter = document.getElementById('mobile-state-filter');
    const allStates = <?php 
        try {
            $states_data = $wpdb->get_results("SELECT DISTINCT state_code, state_name, country_code FROM $table WHERE state_name != '' ORDER BY state_name");
            echo json_encode($states_data ?: []);
        } catch (Exception $e) {
            error_log('Mobile states query failed: ' . $e->getMessage());
            echo '[]';
        }
    ?>;

    function updateMobileStates(countryCode) {
        mobileStateFilter.innerHTML = '<option value="">All</option>';
        if (countryCode) {
            const filteredStates = allStates.filter(s => s.country_code === countryCode);
            filteredStates.forEach(s => {
                const option = document.createElement('option');
                option.value = s.state_code;
                option.textContent = s.state_name;
                if (s.state_code === '<?= $state_filter ?>') {
                    option.selected = true;
                }
                mobileStateFilter.appendChild(option);
            });
            mobileStateFilter.disabled = false;
        } else {
            mobileStateFilter.disabled = true;
        }
    }

    if(mobileCountryFilter) {
        // Auto-select US by default if no country is selected
        if (!mobileCountryFilter.value) {
            mobileCountryFilter.value = 'US';
        }
        updateMobileStates(mobileCountryFilter.value);
    }
});
</script>

<style>
    #mobile-filter-form label {
        display: block;
        margin-top: 1rem;
        margin-bottom: 0.25rem;
        font-weight: 500;
    }
    #mobile-filter-form select {
        width: 100%;
        padding: 0.5rem;
        font-size: 0.9rem;
        border-radius: 6px;
        border: none;
        background: #374151;
        color: #f1f5f9;
    }
    #mobile-filter-form .toggle-buttons {
        display: flex;
        gap: 0.5rem;
        justify-content: center;
        margin-top: 1rem;
    }
</style> 