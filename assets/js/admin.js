/**
 * GitHub Push-to-Deploy Admin JavaScript
 */

(function($) {
    'use strict';
    
    var repositoryIndex = 0;
    
    $(document).ready(function() {
        initEventHandlers();
        updateRepositoryIndexes();
    });
    
    function initEventHandlers() {
        // Test GitHub connection
        $('#test-connection').on('click', function() {
            testGitHubConnection();
        });
        
        // Generate new webhook secret
        $('#generate-secret').on('click', function() {
            generateWebhookSecret();
        });
        
        // Add repository form submit
        $('#add-repo-form').on('submit', function(e) {
            e.preventDefault();
            addRepository();
        });
        
        // Remove repository (new class)
        $(document).on('click', '.js-remove', function() {
            var index = $(this).data('id');
            removeRepositoryNew(index);
        });
        
        // Install repository (new class)
        $(document).on('click', '.js-install', function() {
            var index = $(this).data('id');
            installRepository(index);
        });
        
        // Update repository (new class)
        $(document).on('click', '.js-update', function() {
            var index = $(this).data('id');
            installRepository(index);
        });
        
        // Activate plugin (new class)
        $(document).on('click', '.js-activate', function() {
            var index = $(this).data('id');
            activatePlugin(index);
        });
        
        // Webhook toggle (new class)
        $(document).on('click', '.js-webhook-toggle', function() {
            var index = $(this).data('id');
            var $button = $(this);
            var isActive = $button.text().includes('deactiveren');
            
            if (isActive) {
                removeWebhook(index);
            } else {
                setupWebhook(index);
            }
        });
        
        // Setup webhook
        $(document).on('click', '.setup-webhook', function() {
            var index = $(this).data('index');
            setupWebhook(index);
        });
        
        // Remove webhook
        $(document).on('click', '.remove-webhook', function() {
            var index = $(this).data('index');
            removeWebhook(index);
        });
        
        // Detect slug
        $(document).on('click', '.detect-slug', function() {
            var index = $(this).data('index');
            detectSlug(index);
        });
        
        // Scan repositories
        $('#scan-repositories').on('click', function() {
            scanRepositories();
        });
        
        // Tab functionality
        $('.nav-tab').on('click', function(e) {
            e.preventDefault();
            var tab = $(this).data('tab');
            
            // Remove active class from all tabs and content
            $('.nav-tab').removeClass('nav-tab-active');
            $('.tab-content').removeClass('active');
            
            // Add active class to clicked tab and corresponding content
            $(this).addClass('nav-tab-active');
            $('#' + tab + '-tab').addClass('active');
        });
        
        // Toggle WordPress slug field
        $(document).on('click', '.toggle-slug', function() {
            var index = $(this).data('index');
            var $slugField = $('.slug-field[data-index="' + index + '"]');
            var $button = $(this);
            
            if ($slugField.is(':visible')) {
                $slugField.slideUp();
                $button.text('Toon WordPress Slug');
            } else {
                $slugField.slideDown();
                $button.text('Verberg WordPress Slug');
            }
        });
    }
    
    function testGitHubConnection() {
        var $button = $('#test-connection');
        var $status = $('#connection-status');
        var originalText = $button.text();
        
        $button.prop('disabled', true).text(githubPTD.strings.testing);
        $status.hide();
        
        $.ajax({
            url: githubPTD.ajax_url,
            type: 'POST',
            data: {
                action: 'github_ptd_test_connection',
                nonce: githubPTD.nonce
            },
            success: function(response) {
                if (response.success) {
                    showNotification('✅ Verbonden met GitHub als: ' + response.data.user, 'success');
                } else {
                    showNotification('❌ GitHub verbinding mislukt: ' + response.data, 'error');
                }
            },
            error: function() {
                showNotification('❌ GitHub verbinding mislukt: Onbekende fout', 'error');
            },
            complete: function() {
                $button.prop('disabled', false).text(originalText);
            }
        });
    }
    
    function generateWebhookSecret() {
        var $input = $('input[name="github_ptd_options[webhook_secret]"]');
        var newSecret = generateRandomString(32);
        $input.val(newSecret);
    }
    
    function addRepository() {
        var githubUrl = $('#github-url').val().trim();
        var type = $('#type').val();
        var slug = $('#slug').val().trim();
        
        // Valideer: alleen als zowel URL leeg is als er geen modal selecties zijn
        var modalSelections = getModalSelections();
        
        if (!githubUrl && (!modalSelections || modalSelections.length === 0)) {
            showNotification('Voer een GitHub URL in of selecteer repositories uit de modal', 'error');
            return;
        }
        
        var $button = $('#add-repository');
        var originalText = $button.text();
        
        $button.prop('disabled', true).text('Toevoegen...');
        
        var items = [];
        if (githubUrl) {
            items.push({
                github_url: githubUrl,
                type: type,
                slug: slug
            });
        }
        
        // Voeg modal selecties toe
        items = items.concat(modalSelections);
        
        $.ajax({
            url: githubPTD.ajax_url,
            type: 'POST',
            data: {
                action: 'github_ptd_add_repositories',
                items: items,
                nonce: githubPTD.nonce
            },
            success: function(response) {
                if (response.success) {
                    showNotification('Repository(s) toegevoegd!', 'success');
                    
                    // Add new cards to the list without page reload
                    response.data.forEach(function(repo) {
                        var $newCard = createRepositoryCard(repo, repo.index);
                        $('.repo-list').append($newCard);
                    });
                    
                    // Clear form and modal selections
                    $('#github-url').val('');
                    $('#slug').val('');
                    clearModalSelections();
                } else {
                    showNotification('Fout bij toevoegen repository: ' + response.data, 'error');
                }
            },
            error: function() {
                showNotification('Fout bij toevoegen repository', 'error');
            },
            complete: function() {
                $button.prop('disabled', false).text(originalText);
            }
        });
    }
    
    function getModalSelections() {
        // Return array of selected repositories from modal
        return window.modalSelections || [];
    }
    
    function clearModalSelections() {
        // Clear modal selections
        window.modalSelections = [];
    }
    
    function showNotification(message, type) {
        // Remove existing notifications
        $('.github-ptd-notification').remove();
        
        // Create notification element
        var $notification = $('<div class="github-ptd-notification ' + type + '">' + message + '</div>');
        
        // Add to page
        $('body').append($notification);
        
        // Auto remove after 5 seconds
        setTimeout(function() {
            $notification.fadeOut(300, function() {
                $(this).remove();
            });
        }, 5000);
    }
    
    function removeRepository(index) {
        if (!confirm(githubPTD.strings.confirm_remove)) {
            return;
        }
        
        var $row = $('.repository-row[data-index="' + index + '"]');
        
        // If this is the last repository, just clear the fields
        if ($('#repositories-container .repository-row').length === 1) {
            $row.find('input, select').val('');
            // Save empty repository
            saveRepositories();
            return;
        }
        
        // Remove the row and update indexes
        $row.remove();
        updateRepositoryIndexes();
        
        // Save repositories after removal
        window.autoInstalling = false;
        saveRepositories();
        
        // Also remove from repository management section
        $('.repository-action-item').each(function() {
            var $item = $(this);
            var $button = $item.find('.install-repository, .setup-webhook, .remove-webhook');
            if ($button.data('index') == index) {
                $item.fadeOut(300, function() {
                    $(this).remove();
                });
            }
        });
    }
    
    function updateRepositoryIndexes() {
        $('#repositories-container .repository-row').each(function(newIndex) {
            var $row = $(this);
            var oldIndex = $row.data('index');
            
            if (oldIndex !== newIndex) {
                $row.attr('data-index', newIndex);
                
                // Update form field names
                $row.find('input, select').each(function() {
                    var $field = $(this);
                    var name = $field.attr('name');
                    if (name) {
                        name = name.replace(/\[\d+\]/, '[' + newIndex + ']');
                        $field.attr('name', name);
                    }
                    
                    var id = $field.attr('id');
                    if (id) {
                        id = id.replace(/_\d+_/, '_' + newIndex + '_');
                        $field.attr('id', id);
                    }
                });
                
                // Update remove button
                $row.find('.remove-repository').attr('data-index', newIndex);
            }
        });
    }
    
    
    function installRepository(index, callback) {
        var $button = $('.js-install[data-id="' + index + '"]');
        var originalText = $button.text();
        
        $button.prop('disabled', true).text('Installeren...');
        
        $.ajax({
            url: githubPTD.ajax_url,
            type: 'POST',
            data: {
                action: 'github_ptd_install_repository',
                index: index,
                nonce: githubPTD.nonce
            },
            success: function(response) {
                if (response.success) {
                    showNotification('Repository succesvol geïnstalleerd!', 'success');
                    
                    // Update card without page reload
                    var $card = $('.repo-card[data-index="' + index + '"]');
                    if ($card.length) {
                        // Update badges
                        var $badges = $card.find('.repo-card__badges');
                        $badges.html('<span class="status-badge badge--ok">✅ Geïnstalleerd</span><span class="status-badge badge--ok">🔗 Webhook actief</span>');
                        
                        // Update version info
                        var $body = $card.find('.repo-card__body');
                        $body.html('<p class="repo-card__version">Huidige versie: ' + (response.data.version || 'Unknown') + '</p>');
                        
                        // Update footer buttons
                        var $footer = $card.find('.repo-card__footer');
                        $footer.html(
                            '<button type="button" class="btn js-update" data-id="' + index + '">🔄 Updaten</button>' +
                            '<button type="button" class="btn js-webhook-toggle" data-id="' + index + '">🔗 Webhook deactiveren</button>' +
                            '<button type="button" class="btn btn-danger js-remove" data-id="' + index + '">🗑️ Verwijderen</button>'
                        );
                    }
                } else {
                    showNotification('Fout bij installeren: ' + response.data, 'error');
                }
            },
            error: function() {
                showNotification('Fout bij installeren repository', 'error');
            },
            complete: function() {
                $button.prop('disabled', false).text(originalText);
                
                // Call callback if provided
                if (typeof callback === 'function') {
                    callback();
                }
            }
        });
    }
    
    function setupWebhook(index) {
        var $button = $('.setup-webhook[data-index="' + index + '"]');
        var $status = $('#webhook-status-' + index);
        var originalText = $button.text();
        
        $button.prop('disabled', true).text('Instellen...');
        $status.html('<span class="status-indicator">⏳</span> <span class="status-text">Webhook instellen...</span>');
        
        $.ajax({
            url: githubPTD.ajax_url,
            type: 'POST',
            data: {
                action: 'github_ptd_setup_webhook',
                index: index,
                nonce: githubPTD.nonce
            },
            success: function(response) {
                if (response.success) {
                    $button.text('Webhook verwijderen').removeClass('setup-webhook').addClass('remove-webhook').show();
                    $status.html('<span class="status-indicator">✅</span> <span class="status-text">Webhook actief</span>');
                } else {
                    $button.text(originalText);
                    $status.html('<span class="status-indicator">❌</span> <span class="status-text">Fout: ' + response.data + '</span>');
                }
            },
            error: function() {
                $button.text(originalText);
                $status.html('<span class="status-indicator">❌</span> <span class="status-text">Verbindingsfout</span>');
            },
            complete: function() {
                $button.prop('disabled', false);
            }
        });
    }
    
    function removeWebhook(index) {
        var $button = $('.remove-webhook[data-index="' + index + '"]');
        var $status = $('#webhook-status-' + index);
        var originalText = $button.text();
        
        $button.prop('disabled', true).text('Verwijderen...');
        $status.html('<span class="status-indicator">⏳</span> <span class="status-text">Webhook verwijderen...</span>');
        
        $.ajax({
            url: githubPTD.ajax_url,
            type: 'POST',
            data: {
                action: 'github_ptd_remove_webhook',
                index: index,
                nonce: githubPTD.nonce
            },
            success: function(response) {
                if (response.success) {
                    $button.text('Webhook instellen').removeClass('remove-webhook').addClass('setup-webhook');
                    $status.html('<span class="status-indicator">⚪</span> <span class="status-text">Geen webhook</span>');
                } else {
                    $button.text(originalText);
                    $status.html('<span class="status-indicator">❌</span> <span class="status-text">Fout: ' + response.data + '</span>');
                }
            },
            error: function() {
                $button.text(originalText);
                $status.html('<span class="status-indicator">❌</span> <span class="status-text">Verbindingsfout</span>');
            },
            complete: function() {
                $button.prop('disabled', false);
            }
        });
    }
    
    function detectSlug(index) {
        var $button = $('.detect-slug[data-index="' + index + '"]');
        var $slugInput = $('#repo_slug_' + index);
        var $githubInput = $('#repo_github_url_' + index);
        var $typeSelect = $('#repo_type_' + index);
        var originalText = $button.text();
        
        var githubUrl = $githubInput.val();
        var type = $typeSelect.val();
        
        if (!githubUrl) {
            alert('Voer eerst een GitHub URL in');
            return;
        }
        
        $button.prop('disabled', true).text('Detecteren...');
        
        $.ajax({
            url: githubPTD.ajax_url,
            type: 'POST',
            data: {
                action: 'github_ptd_detect_slug',
                github_url: githubUrl,
                type: type,
                nonce: githubPTD.nonce
            },
            success: function(response) {
                if (response.success) {
                    $slugInput.val(response.data);
                    $button.text('✓ Gedetecteerd').removeClass('button').addClass('button-primary');
                    setTimeout(function() {
                        $button.text(originalText).removeClass('button-primary').addClass('button');
                    }, 2000);
                } else {
                    $button.text('Fout: ' + response.data).removeClass('button').addClass('button-secondary');
                    setTimeout(function() {
                        $button.text(originalText).removeClass('button-secondary').addClass('button');
                    }, 3000);
                }
            },
            error: function() {
                $button.text('Verbindingsfout').removeClass('button').addClass('button-secondary');
                setTimeout(function() {
                    $button.text(originalText).removeClass('button-secondary').addClass('button');
                }, 3000);
            },
            complete: function() {
                $button.prop('disabled', false);
            }
        });
    }
    
    function scanRepositories() {
        var $button = $('#scan-repositories');
        var originalText = $button.text();
        
        $button.prop('disabled', true).text('Scannen...');
        
        $.ajax({
            url: githubPTD.ajax_url,
            type: 'POST',
            data: {
                action: 'github_ptd_scan_repositories',
                nonce: githubPTD.nonce
            },
            success: function(response) {
                if (response.success) {
                    showRepositoryScanResults(response.data);
                    $button.text('✓ Scan voltooid').removeClass('button-primary').addClass('button-secondary');
                    setTimeout(function() {
                        $button.text(originalText).removeClass('button-secondary').addClass('button-primary');
                    }, 3000);
                } else {
                    $button.text('Fout: ' + response.data).removeClass('button-primary').addClass('button-secondary');
                    setTimeout(function() {
                        $button.text(originalText).removeClass('button-secondary').addClass('button-primary');
                    }, 5000);
                }
            },
            error: function() {
                $button.text('Verbindingsfout').removeClass('button-primary').addClass('button-secondary');
                setTimeout(function() {
                    $button.text(originalText).removeClass('button-secondary').addClass('button-primary');
                }, 3000);
            },
            complete: function() {
                $button.prop('disabled', false);
            }
        });
    }
    
    function showRepositoryScanResults(repositories) {
        var html = '<div class="scan-results">';
        html += '<button type="button" class="modal-close" id="modal-close-btn">×</button>';
        html += '<h3>Gevonden WordPress Repositories</h3>';
        html += '<p>Selecteer de repositories die je wilt toevoegen:</p>';
        html += '<div class="repository-list">';
        
        repositories.forEach(function(repo, index) {
            html += '<div class="scan-repository-item">';
            html += '<label>';
            html += '<input type="checkbox" class="repo-checkbox" data-github-url="' + repo.github_url + '" data-name="' + repo.name + '" />';
            html += '<div data-language="' + repo.language + '">';
            html += '<strong>' + repo.name + '</strong>';
            html += '<small>' + repo.description + '</small>';
            html += '</div>';
            html += '</label>';
            html += '</div>';
        });
        
        html += '</div>';
        html += '<button type="button" class="button button-primary" id="add-selected-repos">Geselecteerde toevoegen</button>';
        html += '</div>';
        
        // Toon modal of inline results
        if ($('#scan-results-modal').length === 0) {
            $('body').append('<div id="scan-results-modal" style="display:none;">' + html + '</div>');
        } else {
            $('#scan-results-modal').html(html);
        }
        
        $('#scan-results-modal').show();
        
        // Close modal when clicking outside
        $('#scan-results-modal').on('click', function(e) {
            if (e.target === this) {
                $(this).hide();
            }
        });
        
        // Close modal when clicking close button
        $(document).on('click', '#modal-close-btn', function() {
            $('#scan-results-modal').hide();
        });
        
        // Add selected repositories
        $('#add-selected-repos').on('click', function() {
            var selectedRepos = [];
            $('.repo-checkbox:checked').each(function() {
                selectedRepos.push({
                    github_url: $(this).data('github-url'),
                    name: $(this).data('name'),
                    type: 'plugin', // Default type
                    slug: $(this).data('name').toLowerCase().replace(/[^a-z0-9]/g, '-') // Auto-generate slug
                });
            });
            
            if (selectedRepos.length > 0) {
                // Store selections in global variable
                window.modalSelections = selectedRepos;
                
                // Clear form fields
                $('#github-url').val('');
                $('#slug').val('');
                
                // Trigger form submission
                $('#add-repo-form').trigger('submit');
                
                $('#scan-results-modal').hide();
            } else {
                alert('Selecteer minimaal één repository');
            }
        });
    }
    
    function addScannedRepositories(repositories) {
        // Voeg repositories toe aan het formulier
        repositories.forEach(function(repo) {
            addRepositoryFromScan(repo.github_url, repo.name);
        });
    }
    
    function addRepositoryFromScan(githubUrl, name) {
        var $container = $('#repositories-container');
        var index = $container.children().length;
        
        var html = '<div class="repository-row" data-index="' + index + '">';
        html += '<div class="repository-fields">';
        html += '<div class="field-group">';
        html += '<label for="repo_github_url_' + index + '">GitHub URL (owner/repo):</label>';
        html += '<input type="text" id="repo_github_url_' + index + '" name="github_ptd_options[repositories][' + index + '][github_url]" value="' + githubUrl + '" class="regular-text" placeholder="username/repository" />';
        html += '</div>';
        html += '<div class="field-group">';
        html += '<label for="repo_type_' + index + '">Type:</label>';
        html += '<select id="repo_type_' + index + '" name="github_ptd_options[repositories][' + index + '][type]">';
        html += '<option value="plugin">Plugin</option>';
        html += '<option value="theme">Thema</option>';
        html += '</select>';
        html += '</div>';
        html += '<div class="field-group">';
        html += '<label for="repo_slug_' + index + '">WordPress Slug (auto-detect):</label>';
        html += '<input type="text" id="repo_slug_' + index + '" name="github_ptd_options[repositories][' + index + '][slug]" value="" class="regular-text" placeholder="WordPress slug wordt automatisch gedetecteerd" readonly />';
        html += '<button type="button" class="button detect-slug" data-index="' + index + '">Auto-detect</button>';
        html += '</div>';
        html += '</div>';
        html += '<button type="button" class="button button-link remove-repository" data-index="' + index + '">Verwijderen</button>';
        html += '</div>';
        
        $container.append(html);
        updateRepositoryIndexes();
        
        // Auto-detect slug voor nieuwe repository
        setTimeout(function() {
            detectSlug(index);
        }, 500);
        
        // Auto-save repository en auto-install
        window.autoInstalling = true;
        saveRepositories();
        
        // Auto-install repository na toevoegen
        setTimeout(function() {
            autoInstallRepository(index);
        }, 1000);
    }
    
    function saveRepositories() {
        var repositories = [];
        
        $('#repositories-container .repository-row').each(function() {
            var $row = $(this);
            var index = $row.data('index');
            
            var github_url = $('#repo_github_url_' + index).val();
            var type = $('#repo_type_' + index).val();
            var slug = $('#repo_slug_' + index).val();
            
            // Only save repositories that have a GitHub URL
            if (github_url && github_url.trim() !== '') {
                repositories.push({
                    github_url: github_url,
                    type: type,
                    slug: slug
                });
            }
        });
        
        $.ajax({
            url: githubPTD.ajax_url,
            type: 'POST',
            data: {
                action: 'github_ptd_save_repositories',
                repositories: repositories,
                nonce: githubPTD.nonce
            },
            success: function(response) {
                if (response.success) {
                    console.log('Repositories opgeslagen');
                    // Only reload if we're not in the middle of auto-installation
                    if (!window.autoInstalling) {
                        setTimeout(function() {
                            location.reload();
                        }, 500);
                    }
                }
            },
            error: function() {
                console.log('Fout bij opslaan repositories');
            }
        });
    }
    
    function autoInstallRepository(index) {
        $.ajax({
            url: githubPTD.ajax_url,
            type: 'POST',
            data: {
                action: 'github_ptd_auto_install_repository',
                index: index,
                nonce: githubPTD.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Update status in repository management
                    var $status = $('#version-info-' + index);
                    if ($status.length) {
                        $status.html('<span class="version-label">Status:</span> <span class="up-to-date">✓ Geïnstalleerd</span>');
                    }
                    
                    // Update webhook status
                    var $webhookStatus = $('#webhook-status-' + index);
                    if ($webhookStatus.length) {
                        $webhookStatus.html('<span class="status-indicator">✅</span> <span class="status-text">Webhook actief</span>');
                    }
                    
                    console.log('Repository automatisch geïnstalleerd: ' + response.data);
                    
                    // Reset auto-installing flag and reload page to show updated status
                    window.autoInstalling = false;
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    console.log('Auto-installatie mislukt: ' + response.data);
                    window.autoInstalling = false;
                }
            },
            error: function() {
                console.log('Fout bij auto-installatie');
                window.autoInstalling = false;
            }
        });
    }
    
    function generateRandomString(length) {
        var chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        var result = '';
        for (var i = 0; i < length; i++) {
            result += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        return result;
    }
    
    function createRepositoryCard(repo, index) {
        var html = '<div class="repo-card" data-index="' + index + '">' +
            '<div class="repo-card__header">' +
                '<h3>' + repo.github_url + '</h3>' +
                '<div class="repo-card__badges">' +
                    '<span class="status-badge badge--err">❌ Niet geïnstalleerd</span>' +
                    '<span class="status-badge badge--muted">⚪ Geen webhook</span>' +
                '</div>' +
            '</div>' +
            '<div class="repo-card__body">' +
                '<p class="repo-card__version">Huidige versie: —</p>' +
            '</div>' +
            '<div class="repo-card__footer">' +
                '<button type="button" class="btn btn-primary js-install" data-id="' + index + '">📦 Installeren</button>' +
                '<button type="button" class="btn js-webhook-toggle" data-id="' + index + '">🔗 Webhook activeren</button>' +
                '<button type="button" class="btn btn-danger js-remove" data-id="' + index + '">🗑️ Verwijderen</button>' +
            '</div>' +
        '</div>';
        
        return $(html);
    }
    
    function refreshRepositoryCard(index) {
        // This would refresh a specific card's status
        // For now, we'll reload the page to show updated status
        setTimeout(function() {
            location.reload();
        }, 1000);
    }
    
    function activatePlugin(index) {
        var $button = $('.js-activate[data-id="' + index + '"]');
        var originalText = $button.text();
        
        $button.prop('disabled', true).text('Activeren...');
        
        $.ajax({
            url: githubPTD.ajax_url,
            type: 'POST',
            data: {
                action: 'github_ptd_activate_plugin',
                index: index,
                nonce: githubPTD.nonce
            },
            success: function(response) {
                if (response.success) {
                    showNotification('Plugin geactiveerd!', 'success');
                    
                    // Update card to show update button instead of activate
                    var $card = $('.repo-card[data-index="' + index + '"]');
                    var $footer = $card.find('.repo-card__footer');
                    $footer.html(
                        '<button type="button" class="btn js-update" data-id="' + index + '">🔄 Updaten</button>' +
                        '<button type="button" class="btn js-webhook-toggle" data-id="' + index + '">🔗 Webhook activeren</button>' +
                        '<button type="button" class="btn btn-danger js-remove" data-id="' + index + '">🗑️ Verwijderen</button>'
                    );
                } else {
                    showNotification('Fout bij activeren: ' + response.data, 'error');
                }
            },
            error: function() {
                showNotification('Fout bij activeren plugin', 'error');
            },
            complete: function() {
                $button.prop('disabled', false).text(originalText);
            }
        });
    }
    
    function removeRepositoryNew(index) {
        if (!confirm('Weet je zeker dat je deze repository wilt verwijderen?\n\nDit zal:\n- De repository uit de lijst verwijderen\n- De plugin/thema van de website verwijderen\n- Alle bestanden permanent verwijderen\n\nDeze actie kan niet ongedaan worden gemaakt!')) {
            return;
        }
        
        var $button = $('.js-remove[data-id="' + index + '"]');
        var originalText = $button.text();
        
        $button.prop('disabled', true).text('Verwijderen...');
        
        $.ajax({
            url: githubPTD.ajax_url,
            type: 'POST',
            data: {
                action: 'github_ptd_remove_repository',
                index: index,
                nonce: githubPTD.nonce
            },
            success: function(response) {
                if (response.success) {
                    showNotification('Repository verwijderd!', 'success');
                    
                    // Remove card from DOM
                    var $card = $('.repo-card[data-index="' + index + '"]');
                    $card.fadeOut(300, function() {
                        $(this).remove();
                    });
                } else {
                    showNotification('Fout bij verwijderen: ' + response.data, 'error');
                }
            },
            error: function() {
                showNotification('Fout bij verwijderen repository', 'error');
            },
            complete: function() {
                $button.prop('disabled', false).text(originalText);
            }
        });
    }

})(jQuery);
