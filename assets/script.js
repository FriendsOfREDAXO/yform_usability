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
                        nextId = $next.length ? $next.find('.sort-icon').data('id') : 0;

                    var url = $sortIcon.data('url');

                    $('#rex-js-ajax-loader').addClass('rex-visible');

                    $.post(url, {
                        data_id: $sortIcon.data('id'),
                        filter: $sortIcon.data('filter'),
                        table: $sortIcon.data('table'),
                        table_type: $sortIcon.data('table-type'),
                        table_sort_order: $sortIcon.data('table-sort-order') || null,
                        table_sort_field: $sortIcon.data('table-sort-field') || null,
                        next_id: nextId
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
                fragment: '#rex-js-page-main',
                container: '#rex-js-page-main'
            });
            $(document).on('pjax:end', function () {
                $('#yform_usability-search').find('[name=yfu-term]').focus();
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
