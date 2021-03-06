<?php
/**
 *登录和验证
 *TODO 多学校自动验证
 */
class AuthController extends Rest
{
	/**
	 * 登录注册验证
	 * @method indexAction
	 * @return [type]     [description]
	 * @author NewFuture
	 */
	public function POST_indexAction()
	{
		if (Input::post('number', $number, 'card') && Input::post('password', $password, 'trim'))
		{
			Input::post('sch_id', $sch_id, 'int');
			$safekey = $sch_id . 'auth_' . $number;
			if (!Safe::checkTry($safekey, 5))
			{
				$this->response(0, '尝试次过度,账号临时封禁,12小时后重试，或者联系forbidden@yunyin.org');
			}
			elseif (Input::post('code', $code, 'ctype_alnum'))
			{
				/*输入验证码直接走验证通道*/
				if ($this->verify($number, $password, $sch_id, $code))
				{
					/*验证通过*/
					Safe::del($safekey);
				}
				else
				{
					/*验证失败*/
					$this->response(-1, '学校系统认证失败,请确认您也可登录该系统!');
				}
			}
			else
			{
				$conditon = ['number' => $number];

				$sch_id AND $conditon['sch_id'] = $sch_id; //指定学校

				$user = UserModel::where($conditon)->field('id,password,sch_id,name')->find();
				if (empty($user))
				{
					/*未注册*/
					if ($this->verify($number, $password, $sch_id))
					{
						/*学校验证成功*/
						Safe::del($safekey);
					}
					else
					{
						$this->response(-1, '学校系统认证失败,您可尝试登录该系统!');
					}
				}
				elseif (Encrypt::encryptPwd(md5($password), $number) == $user['password'])
				{
					/*单学校登录成功*/
					Safe::del($safekey);
					$user['number'] = $number;
					$token          = Auth::token($user);
					$sessionid      = Session::start();
					unset($user['password']);
					Session::set('user', $user);
					Cookie::set('token', $token);
					$result = ['sid' => $sessionid, 'user' => $user, 'msg' => '登录成功！', 'token' => $token];
					$this->response(1, $result);
				}
				else
				{
					/*登录失败*/
					$this->response(0, '账号或者密码错误,如果忘记密码，可点击找回!');
				}
			}
		}
		else
		{
			$this->response(-1, '输入学号或者密码无效!');
		}
	}

	/**
	 * 注销
	 * @method logout
	 * @return 重定向或者json字符
	 */
	public function logoutAction()
	{
		Cookie::flush();
		Session::flush();
		$this->response(1, '注销成功!');
	}

	/**
	 * 自动验证
	 * @method POST_autoAction
	 * @return [type]     [description]
	 * @author NewFuture
	 * @todo 完善，自动判断学校
	 */
	public function POST_autoAction()
	{
		if (Input::post('number', $number, 'card') && Input::post('password', $password, 'trim'))
		{
			Input::post('sch_id', $sch_id, 'int');
			$safekey = $sch_id . 'auth_' . $number;
			if (!Safe::checkTry($safekey, 5))
			{
				$this->response(0, '尝试次过度,账号临时封禁,12小时后重试，或者联系forbidden@yunyin.org');
			}
			elseif (Input::post('code', $code, 'ctype_alnum'))
			{
				/*输入验证码直接验证*/
				if ($this->verify($number, $password, $sch_id, $code))
				{
					/*验证通过*/
					Safe::del($safekey);
				}
				else
				{
					$this->response(-1, '学校系统认证失败,请确认您也可登录该系统!');
				}
			}
			elseif ($result = $this->login($number, md5($password), $sch_id))
			{
				/*登录成功*/
				Safe::del($safekey);
			}
			elseif ($sch_id && false === $result)
			{
				/*指定学校后登录失败*/
				$this->response(-1, '密码错误,检查账号或找回密码！');
			}
			elseif ($this->verify($number, $password, $sch_id)) //尝试验证
			{
				/*验证成功*/
				Safe::del($safekey);
			}
			else
			{
				/*注册验证失败*/
				$this->response(-1, '验证出错,请检查学号或密码是否正确,可尝试登录验证的教务系统，或者点忘记密码找回!');
			}
		}
		else
		{
			$this->response(-1, '输入学号或者密码无效!');
		}
	}

	/**
	 * 登录函数
	 * @method login
	 * @access private
	 * @author NewFuture[newfuture@yunyin.org]
	 * @param  [string]   $password    [md5密码]
	 * @return [bool/int] [用户id]
	 */
	private function login($number, $password, $sch_id = null)
	{
		$conditon = ['number' => $number];
		//指定学校
		$sch_id AND $conditon['sch_id'] = $sch_id;

		$users = UserModel::where($conditon)->select('id,password,sch_id,name');
		if (empty($users))
		{
			/*未注册*/
			return null;
		}
		else
		{
			/*验证结果*/
			$password    = Encrypt::encryptPwd($password, $number);
			$reg_schools = [];
			foreach ($users as &$user)
			{
				if ($user['password'] == $password)
				{
					/*登录成功*/

					$user['number'] = $number;
					$token          = Auth::token($user);
					$sessionid      = Session::start();
					unset($user['password']);
					Session::set('user', $user);
					Cookie::set('token', $token);

					// $user['school'] = SchoolModel::getName($user['sch_id']);
					$result = ['sid' => $sessionid, 'user' => $user, 'msg' => '登录成功！', 'token' => $token];
					$this->response(1, $result);
					return true;
				}
				else
				{
					/*验证失败*/
					$sid               = $user['sch_id'];
					$reg_schools[$sid] = School::getAbbr($sid);
				}
			}
			$this->reg_schools = $reg_schools;
			return false;
		}
	}

	/**
	 * 验证准备注册
	 * @method verify
	 * @access public
	 * @author NewFuture[newfuture@yunyin.org]
	 * @return bool|null
	 */
	public function verify($number, $password, $sch_id = null, $code = null)
	{
		$info = array(
			'number'   => $number,
			'password' => $password,
			// 'sch_id' => $sch_id,
		);
		$code AND $info['code'] = $code; //验证码

		if ($sch_id)
		{
			$info['sch_id'] = $sch_id;
			if ($name = School::verify($info))
			{
				$reg = array(
					'number'   => $info['number'],
					'password' => md5($info['password']),
					'name'     => $name,
					'sch_id'   => $sch_id,
				);
				$sid = Session::start();
				Session::set('reg', $reg);
				unset($reg['password']);
				$reg['school'] = SchoolModel::getName($reg['sch_id']);
				$this->response(2, ['sid' => $sid, 'user' => $reg, 'msg' => '验证成功', 'url' => '/user/']);
				return true;
			}
		}
		else
		{
			/*黑名单*/
			$black  = isset($this->reg_schools) ? $this->reg_schools : [];
			$result = School::verify($info, $black);
			if ($result && $result = array_filter($result))
			{
				/*验证成功*/
				//取第一个
				$reg = array(
					'number'   => $info['number'],
					'password' => md5($info['password']),
					'name'     => current($result),
					'sch_id'   => key($result),
				);
				$sid = Session::start();
				Session::set('reg', $reg);

				unset($reg['password']);
				$reg['school'] = SchoolModel::getName($reg['sch_id']);
				$this->response(2, ['sid' => $sid, 'user' => $reg, 'msg' => '验证成功', 'url' => '/user/']);
				return true;
			}
		}

	}
}
?>