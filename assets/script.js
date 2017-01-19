(function ($) {

    $(document).on('rex:ready', function (event, container) {
        initList(event, container);
    });


    function initList(event, container) {
        // status toggle
        if (container.find('.status-toggle').length) {
            container.find('.status-toggle').click(function() {
                var _this = $(this);

                $('#rex-js-ajax-loader').addClass('rex-visible');

                $.post(rex.frontend_url + '?rex-api-call=yform_usability_api&method=changeStatus', {
                    data_id: _this.data('id'),
                    table: _this.data('table'),
                    status: _this.data('status')
                }, function (resp) {
                    $('#rex-js-ajax-loader').removeClass('rex-visible');

                    _this.data('status', resp.message.new_status_val);
                    _this.prop('class', 'status-toggle rex-'+ resp.message.new_status);
                    _this.find('.rex-icon').prop('class', 'rex-icon rex-icon-'+ resp.message.new_status);
                    _this.find('.text').html(resp.message.new_status);
                });
                return false;
            });
        }


        if (container.find('.sortable-list').length) {
            var $this = container.find('.sortable-list');

            $this.find('.sort-icon').parent().addClass('sort-handle');

            Sortable.create($this.find('tbody').get(0), {
                animation: 150,
                handle: '.sort-handle',
                onUpdate: function (e) {
                    var $sort_icon = $(e.item).find('.sort-icon');

                    $('#rex-js-ajax-loader').addClass('rex-visible');

                    $.post(rex.frontend_url + '?rex-api-call=yform_usability_api&method=updateSort', {
                        data_id: $sort_icon.data('id'),
                        table: $sort_icon.data('table'),
                        prio: e.newIndex - e.oldIndex
                    }, function (resp) {
                        $('#rex-js-ajax-loader').removeClass('rex-visible');

                        if (resp.length)
                        {
                            alert(resp);
                        }
                    });
                }
            });
        }
    }

})(jQuery);
