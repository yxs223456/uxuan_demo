var mmg;
$(function () {
    var h = WST.pageHeight();
    var cols = [
        {title:'商品标签', name:'name', width: 10,renderer: function(val,item,rowIndex){
            return "<span><p class='wst-nowrap'>"+item['name']+"</p> </span>"
        }},
        {title:'标签ID', name:'tid', width: 20, renderer: function(val,item,rowIndex){
            return "<span><p class='wst-nowrap'>"+item['tid']+"</p> </span>"
        }},
        {title:'是否显示', name:'isShow', width: 10,renderer: function(val,item,rowIndex){
            return '<input type="checkbox" '+((item['isShow']==1)?"checked":"")+' id="isShow" name="isShow" value="1" class="ipt" lay-skin="switch" lay-filter="isShow1" data="'+item['id']+'" lay-text="显示|隐藏">'
        }},
        {title:'权重', name:'weight', width:5, renderer:function (val,item,rowIndex) {
            return "<span><p class='wst-nowrap'>"+item['weight']+"</p> </span>"
        }},
        {title:'创建时间', name:'createTime', width:30, renderer:function (val,item,rowIndex) {
            return "<span><p class='wst-nowrap'>"+item['createTime']+"</p> </span>"
        }},
        {title:'操作', name:'op' ,width:50, align:'center', renderer: function(val,item,rowIndex){
            var h = "";
            if(WST.GRANT.SPBQ_02)h += "<a class='btn btn-blue' href='javascript:toEdit(" + item['id'] + ")' ><i class='fa fa-pencil'></i>修改</a> ";
            if(WST.GRANT.SPBQ_02)h += "<a class='btn btn-red' href='javascript:toDel(" + item['id'] + ")'><i class='fa fa-trash-o'></i>删除</a> ";
            return h;
        }}
    ];
    mmg = $('.mmg').mmGrid({height: h-85,indexCol: true, cols: cols,method:'POST',
        url: WST.U('admin/goodstags/pageQuery'), fullWidthRows: true, autoLoad: true,
        plugins: [
            $('#pg').mmPaginator({})
        ]
    });
    mmg.on('loadSuccess',function(data){
        layui.form.render();
        layui.form.on('switch(isShow1)', function(data){
            var id = $(this).attr("data");
            if(this.checked){
                toggleIsShow(id, 1);
            }else{
                toggleIsShow(id, 0);
            }
        });
    })
})

function toggleIsShow( id, isShow){
    $.post(WST.U('admin/goodstags/setToggle'), {'id':id, 'isShow':isShow}, function(data, textStatus){
        var json = WST.toAdminJson(data);
        if(json.status=='1'){
            WST.msg("操作成功",{icon:1});
            loadGrid();
        }else{
            WST.msg(json.msg,{icon:2});
        }
    })
}

function loadGrid(){
    mmg.load({page:1});
}
function toEdit(id){
    location.href = WST.U('admin/goodstags/toEdit','id='+id);
}
function toDel(id) {
    var box = WST.confirm({
        content: "您确定要删除该商品标签吗?", yes: function () {
            var loading = WST.msg('正在提交数据，请稍后...', {icon: 16, time: 60000});
            $.post(WST.U('admin/goodstags/del'), {id: id}, function (data, textStatus) {
                layer.close(loading);
                var json = WST.toAdminJson(data);
                if (json.status == '1') {
                    WST.msg("操作成功", {icon: 1});
                    layer.close(box);
                    //loadGrid();
                    location.href=WST.U('Admin/goodstags/index');
                } else {
                    WST.msg(json.msg, {icon: 2});
                }
            });
        }
    });
}
function editInit() {
    /* 表单验证 */
    $('#goodstagsForm').validator({
        fields: {
            name: {
                tip: "请输入商品标签",
                rule: '商品标签:required;length[~10];'
            }
        },
        valid: function (form) {
            var params = WST.getParams('.ipt');
            var loading = WST.msg('正在提交数据，请稍后...', {icon: 16, time: 60000});
            $.post(WST.U('Admin/goodstags/' + ((params.id == 0) ? "add" : "edit")), params, function (data, textStatus) {
                layer.close(loading);
                var json = WST.toAdminJson(data);
                if (json.status == '1') {
                    WST.msg("操作成功", {icon: 1});
                    location.href = WST.U('Admin/goodstags/index');
                } else {
                    WST.msg(json.msg, {icon: 2});
                }
            });
        }
    });
};


