<?php
/**
 * 标签
 */

class TagsController extends Rest
{
	/**
	 * 获取相关标签
	 * @method GET_indexAction
	 * @author NewFuture
	 */
	public function GET_indexAction()
	{
		if ($tags = TagModel::order('count', 'DESC')->select('id,name'))
		{
			$this->response(1, $tags);
		}
		else
		{
			$this->response(0, '没有找到〒_〒');
		}
	}

	/**
	 * 添加标签
	 * @method POST_indexAction
	 * @param  integer          $id [description]
	 * @author NewFuture
	 */
	public function POST_indexAction()
	{
		$uid = $this->auth();
		if (Input::post('name', $name, ''))
		{
			$tag = ['name' => $name, 'use_id' => $uid];
			if ($tid = TagModel::insert($tag))
			{
				$result = ['msg' => '添加成功', 'id' => $tid];
				$this->response(1, $result);
			}
			else
			{
				$this->response(0, '添加失败');
			}
		}
		else
		{
			$this->response(0, '标签名不合法');
		}
	}

	/**
	 * 获取标签详情
	 * @method GET_infoAction
	 * @param  integer        $id [description]
	 * @author NewFuture
	 * @todo 标签引用的书籍
	 */
	public function GET_infoAction($id = 0)
	{
		$uid = $this->auth();
		if ($tag = TagModel::find($id))
		{
			$this->response(1, $tag);
		}
		else
		{
			$this->response(0, '信息已经删除');
		}
	}
}