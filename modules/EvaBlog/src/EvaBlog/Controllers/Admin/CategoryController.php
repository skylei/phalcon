<?php

namespace Eva\EvaBlog\Controllers\Admin;

use Eva\EvaBlog\Models;

class CategoryController extends ControllerBase
{

    /**
     * Index action
     */
    public function indexAction()
    {
        $currentPage = $this->request->getQuery('page', 'int'); // GET
        $limit = $this->request->getQuery('limit', 'int');
        $order = $this->request->getQuery('order', 'int');
        $limit = $limit > 50 ? 50 : $limit;
        $limit = $limit < 10 ? 10 : $limit;

        $items = Models\Category::find(array(
            'order' => 'id DESC',
        ));
        $paginator = new \Eva\EvaEngine\Paginator(array(
            "data" => $items,
            "limit"=> 500,
            "page" => $currentPage
        ));
        $pager = $paginator->getPaginate();
        $this->view->setVar('pager', $pager);
    }

    public function treeAction()
    {
    
    }

    public function createAction()
    {

        $form = new \Eva\EvaBlog\Forms\CategoryForm();
        $category = new Models\Category();
        $form->setModel($category);
        $this->view->setVar('form', $form);

        if(!$this->request->isPost()){
            return false;
        }

        $form->bind($this->request->getPost(), $category);
        if(!$form->isValid()){
            return $this->validHandler($form);
        }
        $category = $form->getEntity();
        try {
            $category->createCategory();
        } catch(\Exception $e) {
            return $this->errorHandler($e, $category->getMessages());
            //return $this->response->redirect($this->getDI()->get('config')->user->registerFailedRedirectUri);
        }
        $this->flashSession->success('SUCCESS_BLOG_CATEGORY_CREATED');
        return $this->redirectHandler('/admin/category/edit/' . $category->id);
    }

    public function editAction()
    {
        $this->view->changeRender('admin/category/create');

        $form = new \Eva\EvaBlog\Forms\CategoryForm();
        $category = Models\Category::findFirst($this->dispatcher->getParam('id'));
        $form->setModel($category);
        $this->view->setVar('form', $form);
        $this->view->setVar('item', $category);
        if(!$this->request->isPost()){
            return false;
        }

        $form->bind($this->request->getPost(), $category);
        if(!$form->isValid()){
            return $this->validHandler($form);
        }
        $category = $form->getEntity();
        $category->assign($this->request->getPost());
        try {
            $category->updateCategory();
        } catch(\Exception $e) {
            return $this->errorHandler($e, $category->getMessages());
        }
        $this->flashSession->success('SUCCESS_BLOG_CATEGORY_UPDATED');
        return $this->redirectHandler('/admin/category/edit/' . $category->id);
    }

    public function deleteAction()
    {
        if(!$this->request->isDelete()){
            $this->response->setStatusCode('405', 'Method Not Allowed');
            $this->response->setContentType('application/json', 'utf-8');
            return $this->response->setJsonContent(array(
                'errors' => array(
                    array(
                        'code' => 405,
                        'message' => 'ERR_POST_REQUEST_METHOD_NOT_ALLOW'
                    )
                ),
            ));
        }

        $id = $this->dispatcher->getParam('id');
        $category =  Models\Category::findFirst($id); 
        try {
            $category->delete();
        } catch(\Exception $e) {
            return $this->jsonErrorHandler($e, $category->getMessages());
        }

        $this->response->setContentType('application/json', 'utf-8');
        return $this->response->setJsonContent($category);
    }
}
