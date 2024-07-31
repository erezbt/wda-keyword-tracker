jQuery(document).ready(function($) {

    let typingTimer;
    const doneTypingInterval = 500; // Time in ms, 500 ms after user stops typing

    // On keyup, start the countdown
    $('#location-input').on('keyup', function() {
        clearTimeout(typingTimer);
        typingTimer = setTimeout(doneTyping, doneTypingInterval);
    });

    // On keydown, clear the countdown
    $('#location-input').on('keydown', function() {
        clearTimeout(typingTimer);
    });

    // User is "finished typing," make the AJAX request
    function doneTyping() {
        const query = $('#location-input').val();
        if (query.length < 3) {
            $('#location-suggestions').hide();
            return;
        }

        $.ajax({
            url: keywordTrackerAjax.ajax_url,
            method: 'POST',
            data: {
                action: 'location_suggestions',
                query: query,
                nonce: keywordTrackerAjax.nonce
            },
            success: function(response) {
                if (response.success) {
                    let suggestions = '';
                    response.data.forEach(location => {
                        // Replace commas with ", " in the canonical name
                        const formattedName = location.canonical_name.replace(/,/g, ', ');
                        suggestions += `<div class="suggestion-item" data-location="${formattedName}">${formattedName}</div>`;
                    });

                    if (suggestions) {
                        $('#location-suggestions').html(suggestions).show();
                    } else {
                        $('#location-suggestions').hide();
                    }
                } else {
                    $('#location-suggestions').hide();
                }
            }
        });
    }

    // Click event for suggestion items
    $(document).on('click', '.suggestion-item', function() {
        const location = $(this).data('location');
        $('#location-input').val(location);
        $('#location-suggestions').hide();
    });

    // Hide suggestions when clicking outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('#location-input, #location-suggestions').length) {
            $('#location-suggestions').hide();
        }
    });

    // Position the suggestion list correctly
    $('#location-input').on('focus', function() {
        
        const inputOffset = $(this).offset();
        console.log(inputOffset);
        $('#location-suggestions').css({
            top: $(this).outerHeight(),
            // left: inputOffset.left
        });
    });

    // Existing form submission and other AJAX handlers...

    // Ensure location suggestions are shown properly
    $('form#add-keyword-form').on('submit', function(e) {
        e.preventDefault();

        var keyword = $('input[name="keyword"]').val();
        var location = $('input[name="location"]').val();
        var search_type = $('select[name="search_type"]').val();

        $.ajax({
            url: keywordTrackerAjax.ajax_url,
            method: 'POST',
            data: {
                action: 'add_and_fetch_keyword',
                keyword: keyword,
                location: location,
                search_type: search_type,
                nonce: keywordTrackerAjax.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#keyword-results tbody').append(
                        '<tr data-id="' + response.data.id + '">' +
                        '<td><input type="checkbox" class="select-row"></td>' +
                        '<td>' + response.data.keyword +
                        '<span class="icon">' +
                        (response.data.search_type === 'mobile' ? 
                        '<span class="dashicons dashicons-smartphone" title="Search Type: Mobile"></span>' :
                        '<span class="dashicons dashicons-desktop" title="Search Type: Desktop"></span>') +
                        (response.data.location ? '<span class="dashicons dashicons-location" title="Location: ' + response.data.location + '"></span>' : '') +
                        '</span></td>' +
                        '<td class="rank">Updating...</td>' +
                        '<td>0</td>' + // Placeholder for 1d change
                        '<td>0</td>' + // Placeholder for 7d change
                        '<td>0</td>' + // Placeholder for 30d change
                        '<td>0</td>' + // Placeholder for life change
                        '<td class="link"><a href="' + response.data.link + '" target="_blank">' + response.data.link + '</a></td>' +
                        '<td>'+
                        '<a href="#" class="button update-keyword" data-id="' + response.data.id + '"><span class="dashicons dashicons-update" style="vertical-align:middle"></span></a> '+
                        '<a href="#" class="button delete-keyword" data-id="' + response.data.id + '"><span class="dashicons dashicons-trash" style="vertical-align:middle"></span></a>' +
                        '<a href="#" class="button show-chart" data-id="' + response.data.id + '"><span class="dashicons dashicons-chart-area" style="vertical-align:middle"></span></a></td>' +
                        '</tr>'
                    );

                    // Poll for updates
                    pollForUpdates(response.data.id);
                } else {
                    alert(response.data);
                }
            }
        });
    });

    $(document).on('click', '.update-keyword', function(e) {
        e.preventDefault();

        // Update the rank column to show "Updating..."
        var row = $(this).closest('tr');
        row.find('.rank').text('Updating...');

        var keywordId = $(this).data('id');

        $.ajax({
            url: keywordTrackerAjax.ajax_url,
            method: 'POST',
            data: {
                action: 'update_keyword',
                keyword_id: keywordId,
                nonce: keywordTrackerAjax.nonce
            },
            success: function(response) {
                if (response.success) {
                    pollForUpdates(keywordId);
                } else {
                    alert(response.data);
                }
            }
        });
    });


    $(document).on('click', '.delete-keyword', function(e) {
        e.preventDefault();

        if (!confirm('Are you sure you want to delete this keyword?')) {
            return;
        }

        var keywordId = $(this).data('id');

        $.ajax({
            url: keywordTrackerAjax.ajax_url,
            method: 'POST',
            data: {
                action: 'delete_keyword',
                id: keywordId,
                nonce: keywordTrackerAjax.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('tr[data-id="' + keywordId + '"]').remove();
                } else {
                    alert(response.data);
                }
            }
        });
    });

    function updateSelectedButtonState() {
        var anySelected = $('.select-row:checked').length > 0;
        $('#update-selected').prop('disabled', !anySelected);
    }

    // Initially disable the button if no checkboxes are selected
    updateSelectedButtonState();

    // Event listener for checkbox changes
    $(document).on('change', '.select-row', function() {
        updateSelectedButtonState();
    });

    // Event listener for the "Select All" checkbox
    $('#select-all').on('change', function() {
        $('.select-row').prop('checked', $(this).prop('checked'));
        updateSelectedButtonState();
    });

    function pollForUpdates(id) {
        setTimeout(function() {
            $.ajax({
                url: keywordTrackerAjax.ajax_url,
                method: 'POST',
                data: {
                    action: 'get_keyword_data',
                    id: id,
                    nonce: keywordTrackerAjax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        var row = $('tr[data-id="' + id + '"]');
                        row.find('.rank').text(response.data.rank === '101' ? '+100' : response.data.rank === '-1' ? 'Updating...' : response.data.rank).attr('data-rank', response.data.rank === '101' ? '101' : response.data.rank === '-1' ? 'Updating...' : response.data.rank); // Update the data-rank attribute;
                        const linkPath = extractPath(response.data.link);
                        row.find('.link a')
                            .attr('href', response.data.link)
                            .text(linkPath);

                        // row.find('.link a').attr('href', response.data.link).text(response.data.link);
                        // Update changes columns
                        row.find('td:nth-child(4)').html(response.data.change_1d).css('color', response.data.change_1d.indexOf('▲') !== -1 ? 'green' : 'red');
                        row.find('td:nth-child(5)').html(response.data.change_7d).css('color', response.data.change_7d.indexOf('▲') !== -1 ? 'green' : 'red');
                        row.find('td:nth-child(6)').html(response.data.change_30d).css('color', response.data.change_30d.indexOf('▲') !== -1 ? 'green' : 'red');
                        row.find('td:nth-child(7)').html(response.data.change_life).css('color', response.data.change_life.indexOf('▲') !== -1 ? 'green' : 'red');

                        sortTable(false);
                    } else {
                        // If still updating, poll again
                        pollForUpdates(id);
                    }
                }
            });
        }, 5000); // Poll every 5 seconds
    }

    $('#update-selected').on('click', function() {
        var selectedIds = $('.select-row:checked').closest('tr').map(function() {
            return $(this).data('id');
        }).get();

        if (selectedIds.length > 0) {
            selectedIds.forEach(function(id) {
                var row = $('tr[data-id="' + id + '"]');
                row.find('.rank').text('Updating...');
            });

            $.ajax({
                url: keywordTrackerAjax.ajax_url,
                method: 'POST',
                data: {
                    action: 'update_selected_keywords',
                    keyword_ids: selectedIds,
                    nonce: keywordTrackerAjax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        selectedIds.forEach(function(id) {
                            pollForUpdates(id);
                        });
                    } else {
                        alert('Failed to update selected keywords.');
                    }
                }
            });
        } else {
            alert('No keywords selected.');
        }
    });

    const table_container =  document.querySelector('#keyword-results-container');
    const table = document.querySelector('#keyword-results');
    const rankHeader = document.querySelector('#rank-header');
    const loadingIndicator = document.querySelector('#loading-indicator');
    let orderAsc = true;

    function sortTable(hideTable = true) {
        if(hideTable){
            loadingIndicator.style.display = 'block';
            table_container.style.display = 'none';
        }
        
        const rowsArray = Array.from(table.querySelectorAll('tbody tr'));
        rowsArray.sort((a, b) => {
            const aRank = parseInt(a.querySelector('.rank').getAttribute('data-rank'), 10);
            const bRank = parseInt(b.querySelector('.rank').getAttribute('data-rank'), 10);

            if (orderAsc) {
                return aRank - bRank;
            } else {
                return bRank - aRank;
            }
        });

        rowsArray.forEach(row => table.querySelector('tbody').appendChild(row));

        // Simulate loading delay
        if(hideTable){
            setTimeout(() => {
                loadingIndicator.style.display = 'none';
                table_container.style.display = 'table';
            }, 500); // Adjust the delay as needed
        }
        
    }

    // Sort table when the page loads
    sortTable();

    rankHeader.addEventListener('click', function() {
        orderAsc = !orderAsc; // Toggle sort order
        sortTable();
    });


    // Function to extract the path from a URL
    function extractPath(url) {
        try {
            const parsedUrl = new URL(url);
            return parsedUrl.pathname;
        } catch (e) {
            return url; // Fallback to the original URL if parsing fails
        }
    }
    let chartInstance;

    // Click event for chart icon
    $(document).on('click', '.show-chart', function(e) {
        e.preventDefault();

        var keywordId = $(this).data('id');

        // Fetch the keyword data for the chart
        $.ajax({
            url: keywordTrackerAjax.ajax_url,
            method: 'POST',
            data: {
                action: 'get_keyword_chart_data',
                keyword_id: keywordId,
                nonce: keywordTrackerAjax.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Destroy existing chart instance if it exists
                    if (chartInstance) {
                        chartInstance.destroy();
                    }

                    // Set the keyword title
                    $('#keyword-title').text(response.data.keyword);

                    var ctx = document.getElementById('rank-chart').getContext('2d');
                    chartInstance = new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: response.data.dates,
                            datasets: [{
                                label: response.data.keyword || '',  // Ensure label is not undefined
                                data: response.data.ranks,
                                borderColor: '#176BFA',
                                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                                fill: false
                            }]
                        },
                        options: {
                            responsive: true,
                            plugins: {
                                legend: {
                                    display: false // Disable the legend
                                },
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            return 'Rank: ' + context.parsed.y;
                                        }
                                    }
                                }
                            },
                            scales: {
                                x: {
                                    type: 'time',
                                    time: {
                                        unit: 'day',
                                        tooltipFormat: 'll'
                                    },
                                    title: {
                                        display: true,
                                        text: 'Date'
                                    }
                                },
                                y: {
                                    beginAtZero: true,
                                    reverse: true,
                                    title: {
                                        display: true,
                                        text: 'Rank'
                                    }
                                }
                            }
                        }
                    });

                    $('#chart-popup').show();
                } else {
                    alert('Failed to retrieve chart data.');
                }
            }
        });
    });

    // Hide the popup when clicking outside of it
    $(document).on('click', function(e) {
        if (!$(e.target).closest('#chart-popup, .show-chart').length) {
            $('#chart-popup').hide();
        }
    });
    
});
