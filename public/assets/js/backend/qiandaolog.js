define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            var jump_id = $("#jump_id").val();
            var isAdmin = $("#is_admin").val();
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'qiandaolog/index/ids/' + jump_id + location.search,
                    // add_url: 'qiandaolog/add',
                    // edit_url: 'qiandaolog/edit',
                    // del_url: 'qiandaolog/del',
                    table: 'qiandaolog',
                }
            });

            var table = $("#table");

            // 初始化表格 xxxsina
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('ID')},
                        {field: 'user_id', title: __('User_id'), operate: false},
                        {field: 'user.username', title: __('User_name'), operate: false},
                        {field: 'device', title: __('Device'), operate: false},
                        {field: 'updatetime', title: __('Updatetime'), operate: false, formatter: Table.api.formatter.datetime},
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