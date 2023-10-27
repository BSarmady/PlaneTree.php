(function ($) {

    $.fn.tree = function (param1, variables = {}, ActionCallback) {
        let _this = this;   // instance of tree
        let $tree = this;   // instance of tree
        let options = {
            checkboxes: false,
            icons: true,
            dragdrop: true
        }

        //region function findNodeById(...)
        function findNodeById(id) {
            if (_this.tree_data && _this.tree_data.length > 0) {

                for (let node of _this.tree_data) {
                    if (node.k === id) {
                        return node;
                    }
                }
            }
            return null;
        }
        //endregion

        //region function render_tree(...)
        function render_tree() {

            //region function renderNode(...)
            function render_node(node_key) {
                let $html = '';
                for (let node of _this.tree_data) {
                    if (node.p !== node_key)
                        continue;
                    let children = render_node(node.k);

                    $html += '<div class="tree-w">' +
                        '<i class="icon' + (children === '' ? '' : ' nav-arrow') + '"></i>' +
                        (options.checkboxes ? '<i class="icon check"  data-checked="u"></i>' : '') +
                        (options.icons ? ' <i class="fa fa-' + (children !== '' ? 'bank' : 'home') + '"></i> ' : '') +
                        '<div class="t" draggable="true" data-id="' + node.k + '" title="' + node.n + '">' + node.d + '</div>';
                    if (children !== '') {
                        $html += '<div class="children collapse"><i class="spacer"></i>' + children + '</div>';
                    }
                    $html += '</div>';
                }
                return $html;
            }
            //endregion

            let $html = '';
            for (let node of _this.tree_data) {
                if (node.p)
                    continue;
                let children = render_node(node.k);
                $html += '<div class="tree-w">' +
                    '<i class="icon' + (children === '' ? '' : ' nav-arrow') + ' open"></i>' +
                    (options.checkboxes ? '<i class="icon check"  data-checked="u"></i>' : '') +
                    (options.icons ? ' <i class="fa fa-globe"></i> ' : '') +
                    '<div class="t" data-id="' + node.k + '" title="' + node.n + '">' + node.d + '</div>';
                if (children !== '')
                    $html += '<div class="children"><i class="spacer"></i>' + children + '</div>';
                $html += '</div>';
            }
            return $html;
        }
        //endregion

        //region function tree_fix_checks(...)
        function tree_fix_checks($node) {
            $node.parentsUntil($tree, '.tree-w').each(function () {
                const $this = $(this); //each node until root of tree
                const checkbox_count = $this.find('.check').length - 1;
                //this shouldn't happen since we call this on parent node of a node, however if somehow child node that is calling this suddenly disappear, then exit
                if (checkbox_count === 0)
                    return;
                // find current status of checkbox on this node
                const current_state = $this.children('.check').attr('data-checked');
                // we -1 total checked and unchecked counts considering state of this checkbox itself
                const checked_count = $this.find('.check[data-checked="c"]').length - 1 * (current_state === 'c');
                const unchecked_count = $this.find('.check[data-checked="u"]').length - 1 * (current_state === 'u');
                // if checked boxes == all check boxes then tick the parent node, if unchecked boxes = all check boxes then un-tick the parent node, otherwise its in 3rd state
                $this.children('.check').attr('data-checked', checked_count === checkbox_count ? 'c' : unchecked_count === checkbox_count ? 'u' : 'm')
            });

        }
        //endregion

        //region function build_tree(...)
        function bind_tree_events() {

            let dragged = null;

            //region Bind Click event
            $tree
                .on('click', 'i, .t', e => {
                    let $node = $(e.target);
                    if ($node.hasClass('nav-arrow')) {
                        // collapse icon click
                        if ($node.hasClass('open')) {
                            $node.removeClass('open')
                                .siblings('.children')
                                .addClass('collapse');
                        } else {
                            // expand icon click
                            $node.addClass('open')
                                .siblings('.children')
                                .removeClass('collapse');
                        }
                    } else if (options.checkboxes && ($node.hasClass('t') || $node.prop('nodeName') === 'I')) {
                        // node with checkbox clicked (select deselect)
                        const $checkbox = $node.parent().children('.check');
                        const is_checked = $checkbox.attr('data-checked') === 'c' ? 'u' : 'c';
                        $checkbox.attr('data-checked', is_checked);

                        // update children status
                        $checkbox.siblings('.children').find('.check').each(function () {
                            $(this).attr('data-checked', is_checked);
                        });
                        // update state of parents too
                        tree_fix_checks($node);
                    } else {
                        // node without checkbox clicked select the node
                        $tree.find('I,.t').removeClass('text-primary');
                        $node.parent().children('.t, I').addClass('text-primary');
                        if (_this.onAction)
                            _this.onAction('click', findNodeById($node.parent().children('.t').data('id')));
                    }
                })
            //endregion

            //region Bind Drag & drop events
            if (options.dragdrop)
                $tree
                    .on('dragstart', '.t', e => {
                        $(e.target).addClass('dragging')
                        dragged = $(e.target).data('id');
                    })
                    .on('dragend', '.t', e => {
                        $(e.target).removeClass('dragging')
                    })
                    .on('dragover', '.t', e => {
                        e.preventDefault();
                    })
                    .on('dragenter', '.t', e => {
                        $(e.target).addClass('drag-over');
                    })
                    .on('dragleave', '.t', e => {
                        $(e.target).removeClass('drag-over');
                    })
                    .on('drop', '.t', e => {
                        $(e.target).removeClass('drag-over');
                        if ($(e.target).hasClass('t') && _this.onAction) {
                            _this.onAction('move', findNodeById(dragged), findNodeById($(e.target).data('id')));
                        }
                        e.preventDefault();
                    });
            //endregion
        }
        //endregion

        //region function activateNode(...)
        function activateNode(node_id) {
            $('.t').removeClass('text-primary')
            let l = $tree.find('.t[data-id="' + node_id + '"]');
            l.parent().children('.t,I').addClass('text-primary')
                .parents().removeClass('collapse');
            if (l.length > 0 && _this.onAction) {
                _this.onAction('click', findNodeById(variables));
            }
        }
        //endregion

        //region function sort_data(...)
        function sort_data(in_data) {
            in_data.sort((a, b) => {

                //region function has_children(...)
                function has_children(node_id) {
                    for (let node of in_data) {
                        if (node.p === node_id) {
                            return 1;
                        }
                    }
                    return 0;
                }
                //endregion

                let a_has_child = has_children(a.k);
                let b_has_child = has_children(b.k);
                if (a_has_child !== b_has_child)
                    return b_has_child - a_has_child;
                return a.d.localeCompare(b.d);
            })
            return in_data;
        }
        //endregion

        if (param1 && param1.toLowerCase() === 'create') {

            //region Set options and create tree $elem.tree('create', options, ActionCallback)
            this.tree_data = [];
            if (variables.icons !== undefined) {
                options.icons = !!variables.icons;
            }
            if (variables.dragdrop !== undefined) {
                options.dragdrop = !!variables.dragdrop;
            }
            if (variables.checkboxes !== undefined) {
                options.checkboxes = !!variables.checkboxes;
            }
            if (ActionCallback !== undefined) {
                _this.onAction = ActionCallback;
            }
            if (variables.data !== undefined) {
                // if options include data, render too
                _this.tree_data = sort_data(variables.data.slice())
                _this.html(render_tree());
            }
            bind_tree_events(_this);
            return this;
            //endregion

        } else if (param1 && param1.toLowerCase() === 'set-active-node') {

            //region activate node $elem.tree('activate', node_id)
            if (variables === undefined)
                return;
            $tree.each(() => {
                activateNode(variables);
            });
            return this;
            //endregion

        } else if (param1 && param1.toLowerCase() === 'get-active-node') {

            //region get active node $elem.tree('get-active-node')
            let ret = [];
            $tree.each(() => {
                const id = $(this).find('.t.text-primary').data('id');
                if (id !== undefined)
                    ret.push(findNodeById(id));
            });
            if (ret.length === 1)
                ret = ret[0];
            return ret;
            //endregion

        } else if (param1 && param1.toLowerCase() === 'get-root') {

            //region get root node $elem.tree('get-root')
            let ret = [];
            $tree.each(() => {
                const id = $(this).find('.t').eq(0).data('id');
                if (id !== undefined)
                    ret.push(findNodeById(id));
            });
            if (ret.length === 1)
                ret = ret[0];
            return ret;
            //endregion

        } else if (param1 && param1.toLowerCase() === 'set-data') {

            //region create tree $elem.tree('refresh-data', data)
            _this.tree_data = sort_data(variables.slice())
            $tree.html(render_tree());
            //endregion

        } else if (param1 && param1.toLowerCase() === 'set-checked') {

            //region create tree $elem.tree('set-checked', data)
            if (variables === undefined)
                return;
            // uncheck all tree first
            $tree.find(".check").each(function () {
                $(this).attr('data-checked', 'u');
            })
            $tree.find(".t").each(function () {
                // check all that are in data
                let $this = $(this);
                let id = $this.data('id');
                if (variables.indexOf(id) > -1) {
                    $this.siblings('.check').attr('data-checked', 'c');
                    tree_fix_checks($this);
                }
            });
            //endregion

        } else if (param1 && param1.toLowerCase() === 'get-checked') {

            //region create tree $elem.tree('get-checked', with_tri_state:true})
            let checked = [];
            let tristate = variables ?? true;
            // if including tristate, then exclude unchecked, otherwise only get those that are checked
            const selector = tristate ? '.check:not([data-checked="u"])' : '.check[data-checked="c"]';
            $tree.find(selector).each(function () {
                checked.push($(this).siblings('.t').data('id'));
            });
            return checked;
            //endregion

        }
    };
}(jQuery));





