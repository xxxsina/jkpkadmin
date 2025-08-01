define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            var jump_id = $("#jump_id").val();
            var isAdmin = $("#is_admin").val();
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'userscorelog/index/ids/' + jump_id + location.search,
                    // add_url: 'userscorelog/add',
                    // edit_url: 'userscorelog/edit',
                    // del_url: 'userscorelog/del',
                    table: 'userscorelog',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('ID')},
                        {field: 'user_id', title: __('User_id'), operate: false},
                        {field: 'username', title: __('User_name'), operate: false},
                        {field: 'score', title: __('Score'), operate: false},
                        {field: 'before', title: __('Before'), operate: false},
                        {field: 'after', title: __('After'), operate: false},
                        {field: 'memo', title: __('Memo'), operate: false},
                        {field: 'createtime', title: __('Createtime'), operate:'RANGE', addclass:'datetimerange', formatter: Table.api.formatter.datetime},
                        // {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
                    ]
                ]
            });

            // 为表格绑定事件
            Table.api.bindevent(table);

        },
        add: function () {
            Controller.api.bindevent();
        },
        edit: function () {
            Controller.api.bindevent();
        },
        import: function () {
            Controller.api.bindevent();
        },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            }
        }
    };
    return Controller;
});