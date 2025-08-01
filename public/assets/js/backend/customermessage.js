define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            var isAdmin = $("#is_admin").val();
            // 设置全局弹窗默认大小 800px 600px
            Fast.config.openArea = ['90%', '90%'];
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'customermessage/index/' + location.search,
                    // add_url: 'customermessage/add',
                    edit_url: 'customermessage/edit',
                    del_url: 'customermessage/del',
                    table: 'customermessage',
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
                        {field: 'realname', title: __('Realname'), operate: false},
                        {field: 'mobile', title: __('Mobile'), operate: false},
                        {
                            field: 'problem',
                            title: __('Problem'),
                            operate: false,
                            formatter: function(value, row, index) {
                                // 截断显示最多10个字符，鼠标悬停显示完整内容
                                if (value && value.length > 10) {
                                    return '<span title="' + value + '">' + value.substring(0, 10) + '...</span>';
                                }
                                return value;
                            }
                        },
                        {
                            field: 'answer',
                            title: __('Answer'),
                            operate: false,
                            formatter: function(value, row, index) {
                                // 截断显示最多10个字符，鼠标悬停显示完整内容
                                if (value && value.length > 10) {
                                    return '<span title="' + value + '">' + value.substring(0, 10) + '...</span>';
                                }
                                return value;
                            }
                        },
                        {
                            field: 'is_overcome',
                            title: __('Is_overcome'),
                            operate: "=",
                            formatter: function(value, row, index) {
                                // 截断显示最多10个字符，鼠标悬停显示完整内容
                                if (value == 1) {
                                    return '<span style="color:#2a46ee;font-weight: bold;">已解决</span>';
                                } else if (value == 2) {
                                    return '<span  style="color:red;">未解决</span>';
                                } else {
                                    return '<span  style="color:yellowgreen;">未评价</span>';
                                }
                            },
                            searchList: {
                                // 定义下拉选项
                                '0': '未评价',
                                '1': '已解决',
                                '2': '未解决'
                            }
                        },
                        {field: 'image', title: __('Attachment'), operate: false, formatter: function(value, row, index) {
                                // 截断显示最多10个字符，鼠标悬停显示完整内容
                                var icon = '';
                                if (row.image) {
                                    icon = '<i class="fa fa-image" title="图片"></i> &nbsp;';
                                }
                                if (row.video) {
                                    icon += '<i class="fa fa-play-circle-o" title="视频"></i>';
                                }
                                return icon;
                            }},
                        {field: 'createtime', title: __('Createtime'), operate:'RANGE', addclass:'datetimerange', formatter: Table.api.formatter.datetime},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
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