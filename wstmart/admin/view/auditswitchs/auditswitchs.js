var mmg;
$(function () {
    var h = WST.pageHeight();
    var cols = [
        {title:'ID', name:'id', width:10},
        {title:'渠道類型', name:'channel', width:30, renderer:function (val,item,rowIndex) {
            return "<span><p class='wst-nowrap'>"+item['channel']+"</p> </span>"
        }},
        {title:'version', name:'version', width:10, renderer:function (val,item,rowIndex) {
            return "<span><p class='wst-nowrap'>"+item['version']+"</p> </span>"
        }},
        {title:'渠道标识', name:'versionCode', width:10, renderer:function (val,item,rowIndex) {
            return "<span><p class='wst-nowrap'>"+item['versionCode']+"</p> </span>"
        }},
        {title:'创建时间', name:'createTime', width:10, renderer:function (val,item,rowIndex) {
            return "<span><p class='wst-nowrap'>"+item['createTime']+"</p> </span>"
        }},
        {title:'操作', name:'op' ,width:50, align:'center', renderer: function(val,item,rowIndex){
            var h = "";
            if(WST.GRANT.SHLB_01)h += "<a class='btn btn-blue' href='javascript:toEdit(" + item['id'] + ")' ><i class='fa fa-pencil'></i>修改</a> ";
            if(WST.GRANT.SHLB_01)h += "<a class='btn btn-red' href='javascript:toDel(" + item['id'] + ")'><i class='fa fa-trash-o'></i>删除</a> ";
            return h;
        }}
    ];
    mmg = $('.mmg').mmGrid({height: h-85,indexCol: true, cols: cols,method:'POST',
        url: WST.U('admin/auditswitchs/pageQuery'), fullWidthRows: true, autoLoad: true,
        plugins: [
            $('#pg').mmPaginator({})
        ]
    });
    mmg.on('loadSuccess',function(data){
        layui.form.render();
        layui.form.on('switch(isShow1)', function(data){
            var id = $(this).attr("data");
            // if(this.checked){
            //     toggleIsShow(id, 1);
            // }else{
            //     toggleIsShow(id, 0);
            // }
        });
    })
})
function toEdit(id){
    location.href = WST.U('admin/auditswitchs/toEdit','id='+id);
}
function toDel(id) {
    var box = WST.confirm({
        content: "您确定要删除该信息吗?", yes: function () {
            var loading = WST.msg('正在提交数据，请稍后...', {icon: 16, time: 60000});
            $.post(WST.U('admin/auditswitchs/del'), {id: id}, function (data, textStatus) {
                layer.close(loading);
                var json = WST.toAdminJson(data);
                if (json.status == '1') {
                    WST.msg("操作成功", {icon: 1});
                    layer.close(box);
                    location.href=WST.U('Admin/auditswitchs/index');
                } else {
                    WST.msg(json.msg, {icon: 2});
                }
            });
        }
    });
}
var isInitUpload = false;

function editInit(){
    /* 表单验证 */
    $('#guideForm').validator({
        fields: {
            version: {
                rule:"required",
                msg:{required:"请输版本号"},
                tip:"请输版本号",
                ok:"",
            },
        },

        valid: function(form){
            var params = WST.getParams('.ipt');
            var loading = WST.msg('正在提交数据，请稍后...', {icon: 16,time:60000});
            $.post(WST.U('admin/auditswitchs/'+((params.id==0)?"add":"edit")),params,function(data,textStatus){
                layer.close(loading);
                var json = WST.toAdminJson(data);
                if(json.status=='1'){
                    WST.msg("操作成功",{icon:1});
                    location.href=WST.U('Admin/auditswitchs/index');
                }else{
                    WST.msg(json.msg,{icon:2});
                }
            });

        }

    });
    function hideCode(){
        $("#versionCode").hide();
    }
};



