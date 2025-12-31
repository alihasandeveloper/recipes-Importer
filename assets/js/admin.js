jQuery(document).ready(function ($) {
    let activeField = null;
    
    // Initialize Select2 for taxonomy dropdowns
    $('.rs-taxonomy-select').select2({
        placeholder: 'Select terms...',
        allowClear: true,
        width: '100%'
    });
    
    // Toggle handlers for custom value switches
    $('#rs-time-custom-toggle').on('change', function() {
        if ($(this).is(':checked')) {
            $('#rs-time-scrape-mode').hide();
            $('#rs-time-custom-mode').show();
        } else {
            $('#rs-time-scrape-mode').show();
            $('#rs-time-custom-mode').hide();
        }
    });
    
    $('#rs-ingredients-custom-toggle').on('change', function() {
        if ($(this).is(':checked')) {
            $('#rs-ingredients-scrape-mode').hide();
            $('#rs-ingredients-custom-mode').show();
        } else {
            $('#rs-ingredients-scrape-mode').show();
            $('#rs-ingredients-custom-mode').hide();
        }
    });
    
    $('#rs-steps-custom-toggle').on('change', function() {
        if ($(this).is(':checked')) {
            $('#rs-steps-scrape-mode').hide();
            $('#rs-steps-custom-mode').show();
        } else {
            $('#rs-steps-scrape-mode').show();
            $('#rs-steps-custom-mode').hide();
        }
    });
    
    // Load Visual Editor
    $('#rs-fetch-btn').on('click', function (e) {
        e.preventDefault();
        const url = $('#rs-target-url').val();
        if (!url) {
            alert('Please enter a URL');
            return;
        }
        // Validate URL format
        try {
            new URL(url);
        } catch (e) {
            alert('Please enter a valid URL (e.g., https://example.com)');
            return;
        }

        // Reset all fields
        $('input[id^="rs-field-"]').val('');
        $('.rs-field-preview').empty();
        $('.rs-taxonomy-select').val(null).trigger('change');
        
        // Reset checkboxes and custom toggles
        $('input[type="checkbox"]').prop('checked', false);
        
        // Reset custom modes to default (scrape mode)
        $('#rs-time-scrape-mode, #rs-ingredients-scrape-mode, #rs-steps-scrape-mode').show();
        $('#rs-time-custom-mode, #rs-ingredients-custom-mode, #rs-steps-custom-mode').hide();
        
        const $iframe = $('#rs-visual-editor-frame');
        const $container = $('#rs-visual-editor-container');
        
        $container.addClass('loading');
        $iframe.attr('src', rsData.ajax_url + '?action=rs_proxy_page&nonce=' + rsData.nonce + '&url=' + encodeURIComponent(url));
        
        $iframe.on('load', function () {
            $container.removeClass('loading');
            $container.show();
        });
        
        const $btn = $(this);
        const $spinner = $btn.next('.spinner');
        $btn.prop('disabled', true).text('Loading...');
        $spinner.addClass('is-active');
        $.ajax({
            url: rsData.ajax_url,
            type: 'POST',
            data: {
                action: 'rs_fetch_url',
                url: url,
                nonce: rsData.nonce
            },
            success: function (response) {
                if (response.success) {
                    $('#rs-visual-editor').slideDown(300);
                    $('#rs-preview-url').text(url);
                    const frame = document.getElementById('rs-site-frame');
                    const blob = new Blob([response.data.html], { type: "text/html; charset=utf-8" });
                    frame.src = URL.createObjectURL(blob);
                    // Scroll to visual editor
                    $('html, body').animate({
                        scrollTop: $('#rs-visual-editor').offset().top - 50
                    }, 500);
                } else {
                    alert('Error loading page: ' + (response.data || 'Unknown error'));
                }
            },
            error: function (xhr, status, error) {
                console.error('AJAX Error:', status, error);
                alert('Network error: ' + error);
            },
            complete: function () {
                $btn.prop('disabled', false).text('Load Visual Editor');
                $spinner.removeClass('is-active');
            }
        });
    });
    // Handle "Select" buttons
    $('.rs-select-btn').on('click', function () {
        const field = $(this).data('field');
        // If clicking same field, toggle off
        if (activeField === field) {
            stopSelection();
            return;
        }
        // Reset all other buttons
        $('.rs-select-btn').removeClass('active').text('Select');
        // Activate this field
        activeField = field;
        $(this).addClass('active').text('Selecting...');
        sendMessageToFrame('startSelect', { field: field });
        $('#rs-current-mode').text('Mode: Selecting ' + field.charAt(0).toUpperCase() + field.slice(1));
    });
    function sendMessageToFrame(action, data = {}) {
        const frame = document.getElementById('rs-site-frame');
        if (frame && frame.contentWindow) {
            frame.contentWindow.postMessage({ action: action, ...data }, '*');
        }
    }
    // Listen for messages from iframe
    window.addEventListener('message', function (event) {
        if (!event.data.action || event.data.action !== 'elementSelected') return;
        if (activeField) {
            const data = event.data;
            const selector = data.selector;
            if (!selector) {
                console.warn('No selector received from iframe');
                return;
            }
            
            // Update the input field
            const $input = $('#rs-field-' + activeField);
            
            // For description field, append to existing value
            if (activeField === 'description') {
                const currentValue = $input.val();
                if (currentValue) {
                    $input.val(currentValue + ',' + selector);
                } else {
                    $input.val(selector);
                }
            } else {
                $input.val(selector);
            }
            
            // Update preview
            updateFieldPreview(activeField, data);
            
            // Auto-stop selection after choosing (except for multi-select fields)
            if (activeField !== 'description' && activeField !== 'ingredients' && activeField !== 'steps') {
                stopSelection();
            } else {
                // For multi-select, show a confirmation
                showFieldSuccess(activeField);
            }
        }
    });
    function updateFieldPreview(field, data) {
        const $preview = $('#rs-preview-' + field);
        if (field === 'image' && data.src) {
            $preview.html('<img src="' + data.src + '" alt="Preview" style="max-width: 100%; max-height: 150px; margin-top: 8px; border-radius: 4px;">');
        } else if (field === 'ingredients' || field === 'steps') {
            if (data.childCount > 0) {
                $preview.html('<div class="rs-count-badge">' + data.childCount + ' items found</div>');
            } else {
                $preview.html('<div class="rs-count-badge">Container selected</div>');
            }
        } else if (data.text) {
            // const truncated = data.text.length > 80 ? data.text.substring(0, 80) + '...' : data.text;
            $preview.html('<div class="rs-text-preview">' + $('<div>').text(data.text).html() + '</div>');
        }
    }
    function showFieldSuccess(field) {
        const $btn = $('.rs-select-btn[data-field="' + field + '"]');
        $btn.text('✓ Selected').addClass('selected-success');
        setTimeout(function () {
            $btn.text('Select Again').removeClass('selected-success');
        }, 1500);
    }
    function stopSelection() {
        sendMessageToFrame('stopSelect');
        if (activeField) {
            const $btn = $('.rs-select-btn[data-field="' + activeField + '"]');
            $btn.removeClass('active').text('Select');
        }
        $('#rs-current-mode').text('Mode: View');
        activeField = null;
    }
    // Create Recipe Post
    $('#rs-save-mapping').on('click', function () {
        const mapping = {
            title: $('#rs-field-title').val(),
            description: $('#rs-field-description').val(),
            image: $('#rs-field-image').val(),
            time: $('#rs-time-custom-toggle').is(':checked') ? '' : $('#rs-field-time').val(),
            ingredients: $('#rs-ingredients-custom-toggle').is(':checked') ? '' : $('#rs-field-ingredients').val(),
            steps: $('#rs-steps-custom-toggle').is(':checked') ? '' : $('#rs-field-steps').val(),
        };
        
        // Collect custom values
        const customValues = {
            time: $('#rs-time-custom-toggle').is(':checked') ? $('#rs-field-time-custom').val() : '',
            ingredients: $('#rs-ingredients-custom-toggle').is(':checked') ? $('#rs-field-ingredients-custom').val() : '',
            steps: $('#rs-steps-custom-toggle').is(':checked') ? $('#rs-field-steps-custom').val() : ''
        };
        
        // Collect checkbox values
        const checkboxes = {
            'adhd-friendly': $('#rs-field-adhd-friendly').is(':checked'),
            'kid-friendly': $('#rs-field-kid-friendly').is(':checked'),
            'pantry-friendly': $('#rs-field-pantry-friendly').is(':checked')
        };
        
        // Collect selected taxonomies from Select2 dropdowns
        const taxonomies = {};
        $('.rs-taxonomy-select').each(function() {
            const name = $(this).attr('name');
            const selectedValues = $(this).val(); // Select2 returns array of selected values
            
            // Extract taxonomy name from input name: rs_taxonomies[taxonomy_name][]
            const match = name.match(/rs_taxonomies\[([^\]]+)\]/);
            if (match && selectedValues && selectedValues.length > 0) {
                const taxonomyName = match[1];
                taxonomies[taxonomyName] = selectedValues;
            }
        });
        
        const url = $('#rs-target-url').val();
        // Validation
        if (!url) {
            alert('Please enter a URL first.');
            return;
        }
        if (!mapping.title) {
            alert('Please select at least a Title field (marked with *).');
            return;
        }
        const $btn = $(this);
        const originalText = $btn.text();
        const $output = $('#rs-result-output');
        $btn.prop('disabled', true).text('Creating Post... Please Wait...');
        $output.html('<div class="notice notice-info inline"><p>Processing your request...</p></div>');
        $.ajax({
            url: rsData.ajax_url,
            type: 'POST',
            data: {
                action: 'rs_create_post',
                url: url,
                mapping: mapping,
                taxonomies: taxonomies,
                customValues: customValues,
                checkboxes: checkboxes,
                nonce: rsData.nonce
            },
            success: function (response) {
                if (response.success) {
                    // Post created successfully
                    const hasWarnings = response.data.warnings;
                    const noticeClass = hasWarnings ? 'notice-warning' : 'notice-success';
                    $output.html('<div class="notice ' + noticeClass + ' inline"><p>' + response.data.message + '</p></div>');
                    // Redirect after a short delay
                    setTimeout(function () {
                        window.location.href = response.data.redirect_url;
                    }, hasWarnings ? 3000 : 1500);
                } else {
                    // Handle error responses
                    if (response.data && response.data.is_duplicate) {
                        // Duplicate post found
                        $output.html('<div class="notice notice-warning inline"><p>' + response.data.message + '</p></div>');
                        if (confirm(response.data.message + '\n\nClick OK to edit the existing post.')) {
                            window.location.href = response.data.redirect_url;
                        }
                    } else {
                        // Regular error
                        const errorMsg = typeof response.data === 'string'
                            ? response.data
                            : (response.data && response.data.message ? response.data.message : 'Unknown error occurred');
                        $output.html('<div class="notice notice-error inline"><p>Error: ' + errorMsg + '</p></div>');
                    }
                }
            },
            error: function (xhr, status, error) {
                console.error('AJAX Error:', status, error);
                $output.html('<div class="notice notice-error inline"><p>Network error: ' + error + '</p></div>');
            },
            complete: function () {
                $btn.prop('disabled', false).text(originalText);
            }
        });
    });
    // Keyboard shortcuts
    $(document).on('keydown', function (e) {
        // ESC to cancel selection
        if (e.key === 'Escape' && activeField) {
            stopSelection();
        }
    });
});
