<?php

namespace Eva\EvaUser\Controllers;


use Eva\EvaUser\Models;
use Eva\EvaUser\Forms;
use Phalcon\Mvc\Model\Criteria;
use Phalcon\Paginator\Adapter\Model as Paginator;

class UserController extends ControllerBase
{
    public function initialize()
    {
        $view = $this->view;
        $view->setViewsDir($this->getDI()->get('modules')->getModulePath('EvaCore') . '/views/_admin/');
        $view->setLayoutsDir('layouts/');
        $view->setLayout('login');
    }


    public function registerAction()
    {
        $form = new Forms\RegisterForm();

        if ($this->request->isPost()) {

            if ($form->isValid($this->request->getPost()) != false) {
                $user = new Models\Login();
                $user->assign(array(
                    'username' => $this->request->getPost('username'),
                    'email' => $this->request->getPost('email'),
                    'password' => $this->request->getPost('password'),
                ));
                if ($user->register()) {
                    $this->flash->success('Register Success');
                    return $this->response->redirect('/admin/dashboard');
                } else {
                    $this->flash->error($user->getMessages());
                    return $this->response->redirect('/admin');
                }
            }
        }

        //$this->view->form = $form;
    }

    public function loginAction()
    {
        if ($this->request->isPost()) {
            $user = new Models\Login();
            $user->assign(array(
                'username' => $this->request->getPost('username'),
                'password' => $this->request->getPost('password'),
            ));
            if($user->login()) {
                if($this->request->getPost('remember')) {
                    $token = $user->getRememberMeToken();
                    if($token) {
                        $this->cookies->set('realm', $token, time() + $user->getTokenExpired());
                    } else {
                        //$this->flashSession->success('success');
                        $this->flashSession->error($user->getMessages());
                    }
                }
                $this->flashSession->success('success');
                return $this->response->redirect('/admin/dashboard');
            } else {
                //$this->flash->error($user->getMessages());
                $this->flashSession->error('abc');
                return $this->response->redirect('/admin');
            }
        }
    }

    public function logoutAction()
    {
    
    }

    public function verifyAction()
    {
        $code = $this->dispatcher->getParam('code');
        $userId = $this->dispatcher->getParam('userId');
        $user = new Models\Login();
        if($user->verifyNewUser($userId, $code)) {
            $this->flash->success('Verify Success');
        } else {
            $this->flash->error('Verify Failed');
        }
        return $this->response->redirect('/user/login');
    }

    public function forgotAction()
    {
        if ($this->request->isPost()) {
            $user = new Models\ResetPassword();
            $user->assign(array(
                'email' => $this->request->getPost('email'),
            ));
            if($user->resetPassword()) {
                return $this->response->redirect('/admin');
            } else {
                $this->flash->error($user->getMessages());
                return $this->response->redirect('/admin');
            }
        }
    }

    /**
    * Index action
    */
    public function indexAction()
    {
        p('user');
    }

    public function resetAction()
    {
        $code = $this->dispatcher->getParam('code');
        $username = $this->dispatcher->getParam('username');

    
    }

    public function dashboardAction()
    {
    
    }

    public function testAction()
    {
        $user = new Models\Login();
        $authIdentity = $user->getAuthIdentity();
        if(!$authIdentity && ($tokenString = $this->cookies->get('realm')->getValue())) {
            if($user->loginWithCookie($tokenString)) {
            } else {
                $this->cookies->delete('realm');
            }
        }
    }

}
