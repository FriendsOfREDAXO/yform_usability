(function ($) {

    $(document).on('rex:ready', function (event, container) {
        initList(event, container);
    });

    function initList(event, container) {

        function updateStatus($this, status, callback) {
            $('#rex-js-ajax-loader').addClass('rex-visible');

            $.post(rex.frontend_url + '?rex-api-call=yform_usability_api&method=changeStatus', {
                data_id: $this.data('id'),
                table: $this.data('table'),
                status: status
            }, function (resp) {
                callback(resp);
                $('#rex-js-ajax-loader').removeClass('rex-visible');
            });
        }

        // status toggle
        if (container.find('.status-toggle').length) {
            var statusToggle = function () {
                var _this = $(this);

                updateStatus(_this, _this.data('status'), function (resp) {
                    var $parent = _this.parent();
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
                var _this = $(this);

                updateStatus(_this, _this.val(), function (resp) {
                    var $parent = _this.parent();
                    $parent.html(resp.message.element);
                    $parent.children('select:first').change(statusChange);
                });
            };
            container.find('.status-select').change(statusChange);
        }


        if (container.find('.sortable-list').length) {
            var $this = container.find('.sortable-list');

            $this.find('.sort-icon').parent().addClass('sort-handle');

            $this.find('tbody').sortable({
                animation: 150,
                handle: '.sort-handle',
                update: function (e, ui) {
                    var $sort_icon = $(ui.item).find('.sort-icon'),
                        $next = $(ui.item).next(),
                        id = 0,
                        prio_td_index = -1,
                        lowest_prio = -1;

                    // find index of prio th
                    $this.find('thead').find('th').each(function (idx, el) {
                        var $a = $(el).find('a'),
                            href = '';
                        if (!$a.length) {
                            return true; // no link, continue
                        }
                        href = $a.attr('href');
                        if (href.indexOf('func=add') !== -1) {
                            return true; // add link, continue
                        }
                        if (href.indexOf('sort=prio') !== -1) {
                            prio_td_index = idx;
                            return false; // found prio th, store index and break
                        }
                    });
                    // find lowest prio
                    if (prio_td_index > -1) {
                        $this.find('tbody').find('tr').find('td:eq(' + prio_td_index + ')').each(function (idx, el) {
                            var prio = parseInt($(el).text());
                            if (lowest_prio < 0 || prio < lowest_prio) {
                                lowest_prio = prio;
                            }
                        });
                    }
                    // set new prio
                    if (lowest_prio > -1) {
                        $this.find('tbody').find('tr').find('td:eq(' + prio_td_index + ')').each(function (idx, el) {
                            $(el).text(lowest_prio + idx);
                        });
                    }

                    $('#rex-js-ajax-loader').addClass('rex-visible');

                    if ($next.length) {
                        id = $next.find('.sort-icon').data('id');
                    }
                    $.post(rex.frontend_url + '?rex-api-call=yform_usability_api&method=updateSort', {
                        data_id: $sort_icon.data('id'),
                        filter: $sort_icon.data('filter'),
                        table: $sort_icon.data('table'),
                        table_type: $sort_icon.data('table-type'),
                        table_sort_order: $sort_icon.data('table-sort-order') || null,
                        table_sort_field: $sort_icon.data('table-sort-field') || null,
                        next_id: id
                    }).done(function (data) {
                        $('#rex-js-ajax-loader').removeClass('rex-visible');
                        if (window.console) {
                            console.log(data);
                        }
                    });
                }
            });
        }
    }
})(jQuery);
