<?php
/**
 * @Author: Davax<23136891@qq.com>
 * @Date:   2019-05-13 14:58:19
 * @Last Modified by:   Davax
 * @Last Modified time: 2019-05-14 18:28:28
 */

namespace app\admin\controller;

use app\api\model\RankingModel;
use app\api\model\ShopListModel;
use app\api\model\ShopModel;
use app\api\model\TypesModel;
use think\App;
use think\Controller;
use think\Validate;

class Ranking extends Controller
{

    public $ranking; //排行榜
    public $type; //行业分类
    public $shop; //商品信息
    public $shopList; //排行榜商品

    public function __construct(App $app = null)
    {
        parent::__construct($app);
        $this->ranking  = new RankingModel();
        $this->type     = new TypesModel();
        $this->shop     = new ShopModel();
        $this->shopList = new ShopListModel();
    }

    /**
     * 显示资源列表
     * @return mixed
     * @throws \think\exception\DbException
     */
    public function lists()
    {
        $name = $this->request->get('title');
        if ($name) {
            $where[] = ['a.title', 'like', "%" . $name . "%"];
        } else {
            $name    = '';
            $where[] = ['1', '=', 1];
        }
        $data = $this->ranking
            ->alias('a')
            ->where($where)
            ->join('__TYPE__ t', 'a.type=t.id', 'LEFT')
            ->field('a.id,a.title,a.status,a.show_time,a.stick,t.name as type,a.image')
            ->order(['stick' => 'asc', 'id' => 'desc'])
            ->paginate(10, false, ['query' => request()->param()]);
        return $this->fetch('/ranking/index', ['data' => $data, 'name' => $name]);
    }

    /**
     * 显示创建资源表单页
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function create()
    {
        //获取分类名称
        $type = $this->type->where(['status' => 1, 'pid' => 0])->field('id,name')->select();
        return $this->fetch('/ranking/create', ['type' => $type]);
    }

    /**
     * 保存新建的资源
     */
    public function save()
    {
        $data     = $this->request->post();
        $validate = new Validate([
            "title|排行榜名称" => "require|max:30",
            "type|类型"     => "require",
        ]);
        if (!$validate->check($data)) {
            $this->error($validate->getError());
        }
        //重复校验
        $num = $this->ranking->where(['title' => $data['title'], 'type' => $data['type']])->count();
        if ($num > 0) {
            $this->error('该排行榜已经存在');
        }
        //图片上传
        $isfile = $_FILES;
        if (empty($isfile['image']['name'])) {
            $this->error('请上传图片');
        } else {
            // 获取表单上传文件
            if ($isfile['image']['error'] == 1) {
                $this->error('图片过大,最大为5M');
            }
            $file = $this->request->file('image');
            $info = $file->validate(['size' => 10485760, 'ext' => 'jpg,png,jpeg'])->move('../public/uploads/');
            if ($info) {
                // 成功上传后 获取上传信息
                $img           = "/uploads/" . $info->getSaveName();
                $data['image'] = str_replace("\\", "/", $img);
            } else {
                // 上传失败获取错误信息
                $this->error($file->getError());
            }
        }
        $data['show_time'] = $data['add_time'] = time();
        $result            = $this->ranking->insert($data, true);
        if ($result) {
            $this->success('排行榜添加成功', url('/ranking/lists'));
        } else {
            $this->error('排行榜添加失败');
        }
    }

    /**
     * 显示编辑资源表单页
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function edit()
    {
        $id = $this->request->get('id');
        //获取分类名称
        $type = $this->type->where(['status' => 1, 'pid' => 0])->field('id,name')->select();
        //获取排行榜数据
        $get = $this->ranking->where('id', $id)->field('id,title,type,image')->find();
        return $this->fetch('/ranking/edit', ['type' => $type, 'get' => $get]);
    }

    /**
     * 保存更新的资源
     */
    public function update()
    {
        $data     = $this->request->post();
        $validate = new Validate([
            "title" => "require|max:30",
            "type"  => "require",
            "id"    => "number|require",
        ]);
        if (!$validate->check($data)) {
            $this->error($validate->getError());
        }
        /**--------------------------上传图片--------------------**/
        $isfile = $_FILES;
        if (!empty($isfile['image']['name'])) {
            if ($isfile['image']['error'] == 1) {
                $this->error('图片过大,最大为5M');
            }
            $file = $this->request->file('image');
            $info = $file->validate(['size' => 10485760, 'ext' => 'jpg,png,jpeg'])->move('../public/uploads/');
            if ($info) {
                //删除原有图片
                if (!empty($data['image'])) {
                    $img = $_SERVER['DOCUMENT_ROOT'] . $data['image'];
                    if (file_exists($img)) {
                        unlink($img);
                    }
                }
                // 成功上传后 获取上传信息
                $img           = "/uploads/" . $info->getSaveName();
                $data['image'] = str_replace("\\", "/", $img);
            } else {
                // 上传失败获取错误信息
                $this->error($file->getError());
            }
        }
        /**--------------------------上传图片结束--------------------**/
        $result = $this->ranking->update($data, ['id' => $data['id']], true);
        if ($result) {
            $this->success('排行榜修改成功', url('/Ranking/lists'));
        } else {
            $this->error('排行榜修改失败');
        }
    }

    /**
     * 删除排行榜
     */
    public function delete()
    {
        $id  = $this->request->get('id');
        $num = $this->shopList->where('list_id', $id)->count();
        if ($num > 0) {
            $this->error('该排行榜有商家，不可删除');
        } else {
            $result = $this->ranking->destroy(['id' => $id]);
            if ($result) {
                $this->success('排行榜删除成功');
            } else {
                $this->error('排行榜删除失败');
            }
        }
    }

    /**
     *  排行置顶/取消置顶
     */
    public function top()
    {
        $data = $this->request->get();
        if ($data['stick'] == 1) {
            $cate = ['stick' => 2, 'edit_time' => time()];
        } else {
            $cate = ['stick' => 1, 'edit_time' => time()];
        }
        $result = $this->ranking->update($cate, ['id' => $data['id']], true);
        if ($result) {
            $this->success('操作成功');
        } else {
            $this->error('操作失败');
        }
    }

    /**
     * 排行关闭/显示
     */
    public function closed()
    {
        $data = $this->request->get();
        if ($data['status'] == 1) {
            $cate = ['status' => 2, 'edit_time' => time()];
        } else {
            $cate = ['status' => 1, 'edit_time' => time()];
        }
        $result = $this->ranking->update($cate, ['id' => $data['id']], true);
        if ($result) {
            $this->success('操作成功');
        } else {
            $this->error('操作失败');
        }
    }

    /**
     * 查看排行商家详情
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function shop()
    {
        $id   = $this->request->get('id');
        $data = $this->shopList->where('list_id', $id)
            ->field('id,image,title,ranking,votes,phone,location')
            ->order('votes', 'desc')
            ->select();
        $title = $this->ranking->where('id', $id)->field('title')->find();
        return $this->fetch('/ranking/show', ['data' => $data, 'list_id' => $id, 'title' => $title['title']]);
    }

    /**
     * 排行榜商家添加保存
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function shopSave()
    {
        // 获取表单
        $data     = $this->request->post();
        $validate = new Validate([
            "title|名称"    => "require|max:30",
            "votes|票数"    => "number|require",
            "location|地址" => "require|max:100",
        ]);
        if (!$validate->check($data)) {
            $this->error($validate->getError());
        }
        /**--------------------------上传图片--------------------**/
        $isfile = $_FILES;
        if (empty($isfile['image']['name'])) {
            $this->error('请上传图片');
        } else {
            // 获取表单上传文件
            if ($isfile['image']['error'] == 1) {
                $this->error('图片过大,最大为5M');
            }
            $file = $this->request->file('image');
            $info = $file->validate(['size' => 10485760, 'ext' => 'jpg,png,jpeg'])->move('../public/uploads/');
            if ($info) {
                // 成功上传后 获取上传信息
                $img           = "/uploads/" . $info->getSaveName();
                $data['image'] = str_replace("\\", "/", $img);
            } else {
                // 上传失败获取错误信息
                $this->error($file->getError());
            }
        }
        /**--------------------------上传图片结束--------------------**/
        $data['add_time'] = time();
        $result           = $this->shopList->insert($data, true);
        if ($result) {
            //从新规划排名
            $list = $this->shopList->where('list_id', $data['list_id'])
                ->field('id')
                ->order('votes', 'desc')
                ->select();
            foreach ($list as $ker => $val) {
                $ranking = ['ranking' => $ker + 1];
                $this->shopList->update($ranking, ['id' => $val['id']], true);
            }
            $this->success('排行榜添加成功', url("/ranking/shop") . "?id=" . $data['list_id']);
        } else {
            $this->error('排行榜添加失败');
        }
    }

    /**
     * 添加商家页面
     */
    public function shopAdd()
    {
        $id = $this->request->get('id');
        return $this->fetch('/ranking/showAdd', ['list_id' => $id]);
    }

    /**
     * 排行商家修改数据
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function shopEdit()
    {
        $id  = $this->request->get('id');
        $get = $this->shopList->where('id', $id)
            ->field('id,title,votes,phone,image,location,list_id')
            ->find();
        return $this->fetch('/ranking/showEdit', ['get' => $get]);
    }

    /**
     * 排行商家修改保存
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function shopUpdate()
    {
        // 获取表单
        $data     = $this->request->post();
        $validate = new Validate([
            "title|名称"    => "require|max:30",
            "votes|票数"    => "number|require",
            "location|地址" => "require|max:100",
        ]);
        if (!$validate->check($data)) {
            $this->error($validate->getError());
        }
        /**--------------------------上传图片--------------------**/
        $isfile = $_FILES;
        if (!empty($isfile['image']['name'])) {
            if ($isfile['image']['error'] == 1) {
                $this->error('图片过大,最大为5M');
            }
            $file = $this->request->file('image');
            $info = $file->validate(['size' => 10485760, 'ext' => 'jpg,png,jpeg'])->move('../public/uploads/');
            if ($info) {
                // 成功上传后 获取上传信息
                $img           = "/uploads/" . $info->getSaveName();
                $data['image'] = str_replace("\\", "/", $img);
                //删除原有图片
                $img = $_SERVER['DOCUMENT_ROOT'] . $data['img'];
                if (file_exists($img)) {
                    unlink($img);
                }
            } else {
                // 上传失败获取错误信息
                $this->error($file->getError());
            }
        } else {
            $data['image'] = $data['img'];
        }
        /**--------------------------上传图片结束--------------------**/
        $result = $this->shopList->update($data, ['id' => $data['id']], true);
        if ($result) {
            //从新规划排名
            $list = $this->shopList->where('list_id', $data['list_id'])
                ->field('id')
                ->order('votes', 'desc')
                ->select();
            foreach ($list as $ker => $val) {
                $ranking = ['ranking' => $ker + 1];
                $this->shopList->update($ranking, ['id' => $val['id']], true);
            }
            $this->success('修改成功', url("/ranking/shop") . "?id=" . $data['list_id']);
        } else {
            $this->error('修改失败');
        }
    }

    /**
     * 删除商家
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function shopDelete()
    {
        $id = $this->request->get('id');
        //获取图片
        $get    = $this->shopList->where('id', $id)->field('image,list_id')->find();
        $result = $this->shopList->destroy(['id' => $id]);
        if ($result) {
            if ($get['image']) {
                if (file_exists($_SERVER['DOCUMENT_ROOT'] . $get['image'])) {
                    unlink($_SERVER['DOCUMENT_ROOT'] . $get['image']);
                }
            }
            //从新规划排名
            $list = $this->shopList->where('list_id', $get['list_id'])
                ->field('id')
                ->order('votes', 'desc')
                ->select();
            foreach ($list as $ker => $val) {
                $ranking = ['ranking' => $ker + 1];
                $this->shopList->update($ranking, ['id' => $val['id']], true);
            }
            $this->success('排行榜删除成功');
        } else {
            $this->error('排行榜删除失败');
        }
    }

    /**
     * 显示一次性添加页面
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */

    public function createAll()
    {
        //获取分类名称
        $type = $this->type->where(['status' => 1, 'pid' => 0])->field('id,name')->select();
        return $this->fetch('/ranking/createAll', ['type' => $type]);
    }

    /**
     * 一次性保存
     */
    public function saveAll()
    {
        // 获取表单
        $data     = $this->request->post();
        $validate = new Validate([
            "title" => "require|max:30",
            "type"  => "require",
        ]);
        if (!$validate->check($data['list'])) {
            $this->error($validate->getError());
        }
        if (empty($data['shop'])) {
            $this->error('商家信息不能为空');
        }
        //排行榜图片
        $isfile = $_FILES;
        if (!empty($isfile['image']['name'])) {
            if ($isfile['image']['error'] == 1) {
                $this->error('图片过大,最大为5M');
            }
            $file = $this->request->file('image');
            $info = $file->validate(['size' => 10485760, 'ext' => 'jpg,png,jpeg'])->move('../public/uploads/');
            if ($info) {
                // 成功上传后 获取上传信息
                $img                   = "/uploads/" . $info->getSaveName();
                $data['list']['image'] = str_replace("\\", "/", $img);
            } else {
                // 上传失败获取错误信息
                $this->error($file->getError());
            }
        } else {
            if (!empty($data['list']['image'])) {
                $data['list']['image'] = $this->downloadImage($data['list']['image']);
            }
        }
        $data['list']['show_time'] = $data['list']['add_time'] = time();
        $result                    = $this->ranking->insert($data['list'], true);
        // 获取刚刚插入的排行榜ID
        $list_id = $this->ranking->getLastInsID();
        if (isset($data['shop'])) {
            foreach ($data['shop'] as $k => $v) {
                //排行榜ID
                $v['list_id'] = $list_id;
                $validate     = new Validate([
                    "title"    => "require|max:30",
                    "votes"    => "number|require",
                    "location" => "require|max:100",
                ]);
                if (!$validate->check($v)) {
                    $this->error($validate->getError());
                }
                /**--------------------------上传图片--------------------**/
                $isfile = $_FILES;
                if (!empty($isfile['image' . $k]['name'])) {
                    if ($isfile['image' . $k]['error'] == 1) {
                        $this->error('图片过大,最大为5M');
                    }
                    $file = $this->request->file("image" . $k);
                    $info = $file->validate(['size' => 10485760, 'ext' => 'jpg,png,jpeg'])->move('../public/uploads/');
                    if ($info) {
                        // 成功上传后 获取上传信息
                        $img        = "/uploads/" . $info->getSaveName();
                        $v['image'] = str_replace("\\", "/", $img);
                    } else {
                        // 上传失败获取错误信息
                        $this->error($file->getError());
                    }
                } else {
                    $v['image'] = $this->downloadImage($v['img']);
                }
                unset($v['img']);
                /**--------------------------上传图片结束--------------------**/
                $result = $this->shopList->insert($v, true);
                if ($result) {
                    //从新规划排名
                    $list = $this->shopList->where('list_id', $v['list_id'])
                        ->field('id')
                        ->order('votes', 'desc')
                        ->select();
                    foreach ($list as $ker => $val) {
                        $ranking = ['ranking' => $ker + 1];
                        $this->shopList->update($ranking, ['id' => $val['id']], true);
                    }
                }
            }
            $this->success('修改成功', url("/ranking/shop") . "?id=" . $list_id);
        } else {
            $this->error('商家信息为空');
        }
    }

    /**
     * 下载图片
     */
    public function downloadImage($url)
    {
        $root = str_replace("\\", "/", $_SERVER['DOCUMENT_ROOT']);
        $path = $root . '/uploads/' . date("Ymd") . '/';
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, '0');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, '0');
        $file = curl_exec($ch);
        curl_close($ch);
        $filename = pathinfo($url, PATHINFO_BASENAME);
        $resource = fopen($path . $filename, 'a');
        fwrite($resource, $file);
        fclose($resource);
        return '/uploads/' . date("Ymd") . '/' . $filename;
    }
}
