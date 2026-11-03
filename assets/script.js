var YformUsability = (function ($) {
    'use strict';

    $(document).on('rex:ready', function (event, container) {
        initSort(container);
        initSearch(container);
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

    // Suchleiste (fragments/yform_usability/search.php). Ohne JS funktionieren Suchfeld, Zeitraum und Chips;
    // das JS blendet Von/Bis aus, fügt Filterzeilen hinzu und sagt die Trefferzahl an.
    var NO_VALUE_OPS = ['empty', 'not_empty', 'checked', 'unchecked', 'set', 'not_set'];
    var LIST_OPS = ['in', 'not_in'];

    function initSearch(container) {
        var $form = $(container || document).find('[data-yfu-search]');
        if (!$form.length || $form.data('yfu-init')) {
            return;
        }
        $form.data('yfu-init', true);

        var $filters = $form.find('[data-yfu-filters]');

        // Trefferzahl erst nach dem Laden in die Statusregion schreiben, damit Screenreader sie ansagen
        var $count = $form.closest('.yfu-search').find('[data-yfu-count]');
        window.setTimeout(function () {
            $count.text($count.data('yfu-count'));
        }, 200);

        // Zeitraum: Von/Bis nur bei „Eigener Zeitraum“
        var $preset = $form.find('[data-yfu-preset]');
        var $range = $form.find('[data-yfu-range]');
        if ($preset.val() === '' && $range.find('input').filter(function () { return this.value !== ''; }).length) {
            $preset.val('custom');
        }
        function toggleRange() {
            var custom = $preset.val() === 'custom';
            $range.prop('hidden', !custom).find('input').prop('disabled', !custom);
        }
        $preset.on('change', toggleRange);
        toggleRange();

        // Fokus nach dem Neuladen (PJAX oder ganz) wieder ins Suchfeld, wenn er von der Suche kam
        var $search = $form.closest('.yfu-search');
        try {
            if (window.sessionStorage.getItem('yfu-focus')) {
                window.sessionStorage.removeItem('yfu-focus');
                $form.find('#yfu-q').trigger('focus');
            }
        } catch (e) {}
        $form.on('submit', rememberFocus);
        $search.on('click', '.yfu-chip, .yfu-search-reset', rememberFocus);
        function rememberFocus() {
            try {
                window.sessionStorage.setItem('yfu-focus', '1');
            } catch (e) {}
        }

        $form.on('click', '[data-yfu-add]', function () {
            var tpl = $form.find('template[data-yfu-template="row"]')[0];
            var $row = $(document.importNode(tpl.content, true).firstElementChild);
            $filters.append($row);
            renumber();
            initWidgets($row);
            $row.find('[data-yfu-field]').trigger('focus');
        });

        $form.on('click', '[data-yfu-remove]', function () {
            $(this).closest('[data-yfu-filter]').remove();
            renumber();
            $form.find('[data-yfu-add]').trigger('focus');
        });

        // Feldwechsel: Bedingung und Werteingabe des Feldtyps einsetzen (Fokus bleibt, Pfeiltasten im Select lösen change aus)
        $form.on('change', '[data-yfu-field]', function () {
            var $row = $(this).closest('[data-yfu-filter]');
            var name = window.CSS && CSS.escape ? CSS.escape(this.value) : this.value;
            var tpl = $form.find('template[data-yfu-template="field"][data-yfu-name="' + name + '"]')[0];
            if (!tpl) {
                return;
            }
            $row.find('[data-yfu-body]').empty().append(document.importNode(tpl.content, true));
            renumber();
            initWidgets($row);
        });

        $form.on('change', '[data-yfu-op]', function () {
            showWidget($(this).closest('[data-yfu-filter]'));
        });

        function renumber() {
            var legend = $filters.data('yfu-legend') || '{0}';
            $filters.children('[data-yfu-filter]').each(function (i) {
                $(this).find('[name], [id], [for]').each(function () {
                    var el = this;
                    ['name', 'id', 'for'].forEach(function (attr) {
                        var value = el.getAttribute(attr);
                        if (value) {
                            el.setAttribute(attr, value.replace(/yfu-f(\[|-)(\d+|__i__)/, 'yfu-f$1' + i));
                        }
                    });
                });
                $(this).children('legend').text(legend.replace('{0}', i + 1));
                labelPickers($(this));
            });
        }

        function initWidgets($row) {
            if ($.fn.selectpicker) {
                $row.find('select.selectpicker').selectpicker();
            }
            labelPickers($row);
            showWidget($row);
        }

        // bootstrap-select: Button bekommt den Namen aus dem Label, das native Select bleibt nur Formularfeld
        function labelPickers($scope) {
            $scope.find('select.selectpicker').each(function () {
                var $button = $(this).closest('.bootstrap-select').find('button.dropdown-toggle');
                var label = $form.find('label[for="' + this.id + '"]').text();
                $button.attr({'aria-label': label, 'data-id': this.id});
                $(this).attr('aria-hidden', 'true');
            });
        }
        labelPickers($filters);
        markHits($search);

        function showWidget($row) {
            var op = $row.find('[data-yfu-op]').val();
            var kind = NO_VALUE_OPS.indexOf(op) >= 0 ? 'none' : (op === 'between' ? 'range' : (LIST_OPS.indexOf(op) >= 0 ? 'list' : 'single'));
            $row.find('[data-yfu-widget]').each(function () {
                var on = this.getAttribute('data-yfu-widget') === kind;
                $(this).prop('hidden', !on).find('input, select').prop('disabled', !on);
                if ($.fn.selectpicker) {
                    $(this).find('select.selectpicker').selectpicker('refresh');
                }
            });
        }
    }

    // Fundstellen in der Liste mit <mark> hervorheben: Suchwörter in allen Zellen, Textfilter in ihrer Spalte
    var MARK_SKIP = /^(SCRIPT|STYLE|MARK|TEXTAREA|SELECT|OPTION|BUTTON)$/;

    function markHits($search) {
        var terms = $search.data('yfu-mark');
        if (!terms || (!terms.all.length && $.isEmptyObject(terms.fields))) {
            return;
        }
        var $root = $search.closest('#rex-js-page-main');
        if (!$root.length) {
            $root = $(document.body);
        }
        var $cells = $root.find('table tbody td');

        if (terms.all.length) {
            var all = termRegex(terms.all);
            $cells.each(function () {
                markIn(this, all);
            });
        }
        $.each(terms.fields, function (field, values) {
            var name = window.CSS && CSS.escape ? CSS.escape(field) : field;
            var regex = termRegex(values);
            $cells.filter('.yform-table-field-name-' + name).each(function () {
                markIn(this, regex);
            });
        });
    }

    // Groß-/Kleinschreibung egal; zwischen zwei Zeichen darf ein weiches Trennzeichen stehen
    function termRegex(terms) {
        var patterns = terms.slice().sort(function (a, b) {
            return b.length - a.length;
        }).map(function (term) {
            return Array.from(term).map(function (ch) {
                return ch.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            }).join('­?');
        });
        return new RegExp(patterns.join('|'), 'giu');
    }

    function markIn(cell, regex) {
        var walker = document.createTreeWalker(cell, NodeFilter.SHOW_TEXT);
        var nodes = [];
        var node;
        while ((node = walker.nextNode())) {
            if (!MARK_SKIP.test(node.parentNode.nodeName)) {
                nodes.push(node);
            }
        }
        nodes.forEach(function (textNode) {
            var text = textNode.nodeValue;
            var fragment = document.createDocumentFragment();
            var last = 0;
            var match;
            regex.lastIndex = 0;
            while ((match = regex.exec(text))) {
                fragment.appendChild(document.createTextNode(text.slice(last, match.index)));
                var mark = document.createElement('mark');
                mark.className = 'yfu-mark';
                mark.textContent = match[0];
                fragment.appendChild(mark);
                last = match.index + match[0].length;
            }
            if (last > 0) {
                fragment.appendChild(document.createTextNode(text.slice(last)));
                textNode.parentNode.replaceChild(fragment, textNode);
            }
        });
    }

    return {};
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
