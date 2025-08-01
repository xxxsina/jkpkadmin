define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            var isAdmin = $("#is_admin").val();
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'scorecalendar/index/ids/' + location.search,
                    // add_url: 'scorecalendar/add',
                    // edit_url: 'scorecalendar/edit',
                    // del_url: 'scorecalendar/del',
                    table: 'scorecalendar',
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
                        {field: 'username', title: __('User_name'), operate: false},
                        {field: 'type', title: __('Type'), operate: '=',
                            formatter: function(value, row, index) {
                                // 这里可以格式化显示值
                                return value;
                            },
                            searchList: {
                                // 定义下拉选项
                                'check_in': '签到',
                                'add_score': '赚取更多'
                            }},
                        {field: 'numb', title: __('Numb'), operate: false},
                        {field: 'is_complete', title: __('Complete'), operate: '=',
                            formatter: function(value, row, index) {
                                // 这里可以格式化显示值
                                return value == 1 ? '<font color="green"><b>是</b></font>' : '<font color=red>否</font>';
                            },
                            searchList: {
                                // 定义下拉选项
                                '1': '是',
                                '0': '否'
                            }},
                        {field: 'date_stamp', title: __('Date_stamp'), operate:'RANGE', addclass:'datetimerange'},
                        // {field: 'updatetime', title: __('Updatetime'), operate: false, formatter: Table.api.formatter.datetime},
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