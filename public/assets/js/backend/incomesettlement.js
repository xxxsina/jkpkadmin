define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'incomesettlement/index' + location.search,
                    // add_url: 'incomesettlement/add',
                    edit_url: 'incomesettlement/edit',
                    // del_url: 'incomesettlement/del',
                    // multi_url: 'incomesettlement/multi',
                    // import_url: 'incomesettlement/import',
                    table: 'income_and_settlement',
                }
            });

            var table = $("#table");
            table.on('load-success.bs.table', function (e, data) {//在表格数据加载成功后 data为数据
                //统计核算显示到模板页面
                $("#toolbar .total").remove(); //防着刷新后 生成多余的统计单元
                $("#toolbar").append('<a href="javascript:;" class="btn btn-default total" style="font-size:14px;color:dodgerblue;">' +
                    '合计：<span>'+data.sum+'元 </span></a>');//用js在按钮旁边加一个统计的单元 参照K神的demo
            });

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                fixedColumns: true,
                fixedRightNumber: 1,
                columns: [
                    [
                        {checkbox: true},
                        // {field: 'id', title: __('Id')},
                        {field: 'date_stamp', title: __('Date_stamp'), operate: 'RANGE', addclass:'datetimerange', autocomplete:false},
                        {field: 'income', title: __('Income'), operate:'BETWEEN'},
                        {field: 'settlement_yes', title: __('Settlement_yes'), operate:'BETWEEN'},
                        {field: 'settlement_no', title: __('Settlement_no'), operate:'BETWEEN'},
                        {field: 'jl_price', title: __('Jl_price'), operate:'BETWEEN'},
                        {field: 'jl_num', title: __('Jl_num')},
                        {field: 'jl_more_price', title: __('Jl_more_price'), operate:'BETWEEN'},
                        {field: 'jl_more_num', title: __('Jl_more_num')},
                        {field: 'kp_money', title: __('Kp_money'), operate:'BETWEEN'},
                        {field: 'xx_money', title: __('Xx_money'), operate:'BETWEEN'},
                        {field: 'banner_money', title: __('Banner_money'), operate:'BETWEEN'},
                        {field: 'cp_money', title: __('Cp_money'), operate:'BETWEEN'},
                        {field: 'jl_money', title: __('Jl_money'), operate:'BETWEEN'},
                        {field: 'jl_more_money', title: __('Jl_more_money'), operate:'BETWEEN'},
                        {field: 'updatetime', title: __('Updatetime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'createtime', title: __('Createtime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
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
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            }
        }
    };
    return Controller;
});
