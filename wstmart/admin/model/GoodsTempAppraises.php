<?php
/**
 * Created by PhpStorm.
 * User: chenxj
 * Date: 2018/8/23
 * Time: 11:52
 */

namespace wstmart\admin\model;

use wstmart\admin\validate\GoodsTempAppraises as validate;
use wstmart\common\model\Goods as G;
use wstmart\common\exception\AppException as AE;
use think\Db;

class GoodsTempAppraises extends Base
{
    public function lists()
    {
        $rs = $this->where(['dataFlag'=>1])->paginate(input('limit/d'));
        return $rs;
    }

    /**
     * 显示隐藏
     */
    public function setToggle()
    {
        $guideId = input('post.id/d');
        $isShow = input('post.isShow/d');
        $result = $this->where(['id'=>$guideId])->setField("isShow", $isShow);
        if(false !== $result){
            return WSTReturn("设置成功", 1);
        }else{
            return WSTReturn($this->getError(),-1);
        }
    }

    /**
     * 根据ID获取
     */
    public function getById($id)
    {
        $obj = null;
        if($id>0){
            $obj = $this->get(['id'=>$id,"dataFlag"=>1]);
        }
        return $obj;
    }

    public function add($data)
    {
        $m = new G();
        $goodsInfo = $m->getGoodsById($data['goodsId']);
        if (!$goodsInfo) throw AE::factory(AE::GOODS_NOT_EXISTS);
        for ($i=1;$i<=6;$i++) {
            $images[] = $data['images'.$i];
            unset($data['images'.$i]);
        }
        $data['images'] = implode(',',$images);
        $data["dataFlag"] = 1;
        Db::startTrans();
        try {
            $validate = new validate();
            if(!$validate->scene('add')->check($data))return WSTReturn($validate->getError());
            if (empty($data['createTime'])) {
                $data['createTime'] = date('Y-m-d H:i:s');
            }
            $this->save($data);
            $this->afterGoodaTempScores($data['goodsId'], $data['goodsScore'],$data['timeScore'],$goodsInfo->shopId);
            Db::commit();
            return WSTReturn("新增成功", 1);
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            return WSTReturn($e->getMessage(),-1);
        }
    }

    public function edit($data)
    {
        $id = $data['id'];
        WSTUnset($data,'createTime');
        $validate = new validate();
        if(!$validate->scene('edit')->check($data))return WSTReturn($validate->getError());
        $this->allowField(true)->save($data,['id'=>$id]);
        return WSTReturn("编辑成功", 1);
    }

    public function afterGoodaTempScores($goodsId, $goodsScore,$timeScore,$shopId,$serviceScore=0)
    {
        $info = Db::name('goods_temp_scores')->where('goodsId',$goodsId)->find();
        if (!$info) {
            $data = [
                'goodsId'=>$goodsId,'shopId'=>$shopId,'totalScore'=>$goodsScore+$timeScore,'totalUsers'=>1,'goodsScore'=>$goodsScore,
                'goodsUsers'=>1,'serviceScore'=>$serviceScore,'serviceUsers'=>1,'timeScore'=>$timeScore,'timeUsers'=>1
            ];
            Db::name('goods_temp_scores')->insert($data, true);
        } else {
            $prefix = config('database.prefix');
            $updateSql = "update ".$prefix."goods_temp_scores set
                         totalScore=totalScore+".(int)($goodsScore+$serviceScore+$timeScore).",
                         goodsScore=goodsScore+".(int)$goodsScore.",
                         serviceScore=serviceScore+".(int)$serviceScore.",
                         timeScore=timeScore+".(int)$timeScore.",
                         totalUsers=totalUsers+1,goodsUsers=goodsUsers+1,serviceUsers=serviceUsers+1,timeUsers=timeUsers+1
                         where goodsId=".$goodsId;
            Db::execute($updateSql);
        }
    }

    /**
     * 删除
     */
    public function del($id){
        Db::startTrans();
        try {
            $info = $this->where('id', $id)->find();
            $prefix = config('database.prefix');
            $updateSql = "update ".$prefix."goods_temp_scores set
                         totalScore=totalScore-".(int)($info->goodsScore+$info->serviceScore+$info->timeScore).",
                         goodsScore=goodsScore-".(int)$info->goodsScore.",
                         serviceScore=serviceScore-".(int)$info->serviceScore.",
                         timeScore=timeScore-".(int)$info->timeScore.",
                         totalUsers=totalUsers-1,goodsUsers=goodsUsers-1,serviceUsers=serviceUsers-1,timeUsers=timeUsers-1
                         where goodsId=".$info->goodsId;
            Db::execute($updateSql);
            $this->where('id', $id)->update(['dataFlag'=>0]);
            Db::commit();
            return WSTReturn("删除成功", 1);
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            return WSTReturn($e->getMessage(),-1);
        }

    }
}