var mmg;
$(function () {
    var h = WST.pageHeight();
    var cols = [
        {title:'ID', name:'id', width:10},
        {title:'系統类型', name:'type', width:30, renderer:function (val,item,rowIndex) {
            return "<span><p class='wst-nowrap'>"+item['type']+"</p> </span>"
        }},
        // {title:'路径', name:'path', width:30, renderer:function (val,item,rowIndex) {
        //     return '<img src=\"'+WST.conf.ROOT+'/'+item['path']+'\" width=\"50\" />'
        // }},
        {title:'版本号', name:'versionNumber', width:10, renderer:function (val,item,rowIndex) {
            return "<span><p class='wst-nowrap'>"+item['versionNumber']+"</p> </span>"
        }},
        {title:'升级类型', name:'upgradeType', width:10, renderer:function (val,item,rowIndex) {
            return "<span><p class='wst-nowrap'>"+item['upgradeType']+"</p> </span>"
        }},
        {title:'升级说明', name:'text', width:10, renderer:function (val,item,rowIndex) {
            return "<span><p class='wst-nowrap'>"+item['text']+"</p> </span>"
        }},
        {title:'创建时间', name:'createTime', width:10, renderer:function (val,item,rowIndex) {
            return "<span><p class='wst-nowrap'>"+item['createTime']+"</p> </span>"
        }},
        {title:'操作', name:'op' ,width:50, align:'center', renderer: function(val,item,rowIndex){
            var h = "";
            if(WST.GRANT.SJGLPZ_01)h += "<a class='btn btn-blue' href='javascript:toEdit(" + item['id'] + ")' ><i class='fa fa-pencil'></i>修改</a> ";
            if(WST.GRANT.SJGLPZ_01)h += "<a class='btn btn-red' href='javascript:toDel(" + item['id'] + ")'><i class='fa fa-trash-o'></i>删除</a> ";
            return h;
        }}
    ];
    mmg = $('.mmg').mmGrid({height: h-85,indexCol: true, cols: cols,method:'POST',
        url: WST.U('admin/versionups/pageQuery'), fullWidthRows: true, autoLoad: true,
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
    location.href = WST.U('admin/versionups/toEdit','id='+id);
}
function toDel(id) {
    var box = WST.confirm({
        content: "您确定要删除该版本吗?", yes: function () {
            var loading = WST.msg('正在提交数据，请稍后...', {icon: 16, time: 60000});
            $.post(WST.U('admin/versionups/del'), {id: id}, function (data, textStatus) {
                layer.close(loading);
                var json = WST.toAdminJson(data);
                if (json.status == '1') {
                    WST.msg("操作成功", {icon: 1});
                    layer.close(box);
                    location.href=WST.U('Admin/versionups/index');
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
            versionNumber: {
                rule:"required",
                msg:{required:"请输版本号"},
                tip:"请输版本号",
                ok:"",
            },
        },

        valid: function(form){
            var params = WST.getParams('.ipt');
            var loading = WST.msg('正在提交数据，请稍后...', {icon: 16,time:60000});
            $.post(WST.U('admin/versionups/'+((params.id==0)?"add":"edit")),params,function(data,textStatus){
                layer.close(loading);
                var json = WST.toAdminJson(data);
                if(json.status=='1'){
                    WST.msg("操作成功",{icon:1});
                    location.href=WST.U('Admin/versionups/index');
                }else{
                    WST.msg(json.msg,{icon:2});
                }
            });

        }

    });
    // $("#versionNumber").blur(function(){
    //     versionNumber = ($(this).val());
    //     alert(versionNumber);
    // });
    //initTime('#createTime',opts.createTime);
//文件上传
    WST.upload({
        pick:'#guidePicker',
        formData: {dir:'versionups'},
        //fileSingleSizeLimit:,
        accept: {title:'Applications',extensions: 'apk',mimeTypes: 'application/apk'},
        server:WST.U('admin/index/uploadApk'),
        callback:function(f){
            var json = WST.toAdminJson(f);
            if(json.status==1){
                $('#uploadMsg').empty().hide();
                //保存上传的图片路径
                $('#path').val(json.route+json.name);
                $('#preview').html(json.route+json.name);
            }else{
                WST.msg(json.msg,{icon:2});
            }
        },
        progress:function(rate){
            $('#uploadMsg').show().html('已上传'+rate+"%");
        }
    });

    function initTime($id,val){
        var html = [],t0,t1;
        var str = val.split(':');
        for(var i=0;i<24;i++){
            t0 = (val.indexOf(':00')>-1 && (parseInt(str[0],10)==i))?'selected':'';
            t1 = (val.indexOf(':30')>-1 && (parseInt(str[0],10)==i))?'selected':'';
            html.push('<option value="'+i+':00" '+t0+'>'+i+':00</option>');
            html.push('<option value="'+i+':30" '+t1+'>'+i+':30</option>');
        }
        $($id).append(html.join(''));
    }
};


