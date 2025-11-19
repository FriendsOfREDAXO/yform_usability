var YformUsability = (function ($) {
    'use strict';

    $(document).on('rex:ready', function (event, container) {
        initSort(container);
        initSearch();
        initStatusToggle(container);
        initSelect2();
        initSettings();
    });

    function initSelect2() {
        if ($().select2) {
            window.setTimeout(function() {
                $('#rex-page-yform-manager-table-field select').each(function() {
                    if ($(this).children('option').length > 6) {
                        $(this).select2();
                    }
                });
                $('#rex-page-yform-manager-usability select').select2();
            }, 500);
        }else{
            // not select2 -> listen for yfu-term enter event
            $('#yform_usability-search').find('[name=yfu-term]').on("keypress", function(event) {
                if (event.key === "Enter") {
                    $(this).closest('form').submit();
                }
            });
        }
    }

    function initSettings() {
        var $container = $('#rex-page-yform-manager-usability');

        if ($container.length) {
            window.setTimeout(function () {
                $container.find('[data-toggle-wrapper] input[type=checkbox]').change(function () {
                    var $wrapper = $(this).parents('[data-toggle-wrapper]'),
                        $select = $wrapper.find('.rex-form-group:eq(1)');

                    if ($(this).is(':checked')) {
                        $select.addClass('hide');
                    } else {
                        $select.removeClass('hide');
                    }
                }).trigger('change');
            }, 500);
        }
    }

    function updateDatasetStatus($this, status, url, callback) {
        $('#rex-js-ajax-loader').addClass('rex-visible');
        $.post(url, {
            data_id: $this.data('id'),
            table: $this.data('table'),
            status: status
        }, function (resp) {
            callback(resp);
            $('#rex-js-ajax-loader').removeClass('rex-visible');
        });
    }

    function initStatusToggle(container) {
        // status toggle
        if (container.find('.status-toggle').length) {
            var statusToggle = function () {
                var $this = $(this);
                var url = container.find('.status-toggle').data('api-url');

                updateDatasetStatus($this, $this.data('status'), url, function (resp) {
                    var $parent = $this.parent();
                    $parent.html(resp.message.element);
                    $parent.children('a:first').click(statusToggle);
                });
                return false;
            };
            container.find('.status-toggle').click(statusToggle);
        }


        // status select
        if (container.find('.status-select').length) {
            var statusChange = function () {
                var $this = $(this);
                var url = container.find('.status-select').data('api-url');

                updateDatasetStatus($this, $this.val(), url, function (resp) {
                    var $parent = $this.parent();
                    $parent.html(resp.message.element);
                    $parent.children('select:first').change(statusChange);
                });
            };
            container.find('.status-select').change(statusChange);
        }
    }

    function initSort(container) {
        if (container.find('.sortable-list').length) {
            var $this = container.find('.sortable-list');

            $this.find('.sort-icon').parent().addClass('sort-handle');

            Sortable.create($this.find('tbody').get(0), {
                group: false,
                handle: '.sort-handle',
                direction: 'vertical',
                onUpdate: function (evt) {
                    var $sortIcon = $(evt.item).find('.sort-icon'),
                        $next = $(evt.from).children(':eq(' + (evt.newIndex + 1) + ')'),
                        nextId = 0;
                        
                    // Check if element was moved to the end of the current page
                    if (!$next.length) {
                        // Element is moved to end of page - check if there are more pages after this one
                        var $pagination = $('.rex-page').find('.pagination');
                        var $nextPageLink = $pagination.find('.next:not(.disabled)');
                        
                        if ($nextPageLink.length > 0) {
                            // There are more pages - we need to find the first item of the next page
                            // For now, set nextId to 0 to use fallback behavior
                            nextId = 0;
                        } else {
                            // This is the last page - element should go to the absolute end
                            nextId = 0;
                        }
                    } else {
                        // There's a next element on the same page
                        nextId = $next.find('.sort-icon').data('id');
                    }

                    var url = $sortIcon.data('url');

                    $('#rex-js-ajax-loader').addClass('rex-visible');

                    $.post(url, {
                        data_id: $sortIcon.data('id'),
                        filter: $sortIcon.data('filter'),
                        table: $sortIcon.data('table'),
                        table_type: $sortIcon.data('table-type'),
                        table_sort_order: $sortIcon.data('table-sort-order') || null,
                        table_sort_field: $sortIcon.data('table-sort-field') || null,
                        next_id: nextId,
                        // Add pagination context to help backend understand current page
                        current_page_start: new URLSearchParams(window.location.search).get('start') || 0,
                        current_page_amount: $(evt.from).children().length
                    }).done(function (data) {
                        $('#rex-js-ajax-loader').removeClass('rex-visible');
                    });
                }
            });
        }
    }

    function initSearch() {
        var $form = $('#yform_usability-search');

        $form.on('submit', function (evt) {
            $.pjax.submit(evt, {
                push: true,
                fragment: '.panel-default',
                container: '.panel-default'
            });
            $(document).on('pjax:end', function () {
                $('#yform_usability-search').find('[name=yfu-term]').focus();
                $('#yform_usability-search').addClass('filtered');
            });
            return false;
        });
    }

    return {
        doYformSearch: function (_this, event) {
            if ($('#yform_usability-search').find('[name=yfu-term]').val() != '') {
                $('#yform_usability-search').submit();
            }
        },

        resetYformSearch: function (_this) {
            var $form = $(_this).parents('form');
            $form.find('[name=yfu-term]').val('');
            $form.submit();
        }
    };
})(jQuery);

// Global function for field duplication with name prompt
window.yformDuplicateField = function(tableName, fieldId, originalName, csrfParam) {
    var newName = prompt('Neuen Feldnamen eingeben:', originalName + '_copy');
    
    if (newName === null) {
        return; // User cancelled
    }
    
    newName = newName.trim();
    
    if (newName === '') {
        alert('Feldname darf nicht leer sein.');
        return;
    }
    
    // Validate field name
    if (!/^[a-zA-Z][a-zA-Z0-9_]*$/.test(newName)) {
        alert('Ungültiger Feldname. Nur Buchstaben, Zahlen und Unterstriche erlaubt. Muss mit einem Buchstaben beginnen.');
        return;
    }
    
    // Build URL from parameters to avoid & escaping issues
    var url = 'index.php?page=yform/manager/table_field' +
              '&table_name=' + encodeURIComponent(tableName) +
              '&func=duplicate' +
              '&field_id=' + fieldId +
              '&' + csrfParam +
              '&new_name=' + encodeURIComponent(newName);
    
    window.location.href = url;
};
