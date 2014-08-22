<?php

namespace WscnApiVer2\Controllers;

use Swagger\Annotations as SWG;
use Eva\EvaLivenews\Models;
use Eva\EvaLivenews\Models\NewsManager;
use Eva\EvaLivenews\Forms;
use Eva\EvaEngine\Exception;

/**
 * @package
 * @category
 * @subpackage
 *
 * @SWG\Resource(
 *  apiVersion="0.2",
 *  swaggerVersion="1.2",
 *  resourcePath="/livenews",
 *  basePath="/v2"
 * )
 */
class LivenewsController extends ControllerBase
{
    /**
     *
     * @SWG\Api(
     *   path="/livenews",
     *   description="Livenews related api",
     *   produces="['application/json']",
     *   @SWG\Operations(
     *     @SWG\Operation(
     *       method="GET",
     *       summary="Get livenews list",
     *       notes="Returns livenews list",
     *       @SWG\Parameters(
     *         @SWG\Parameter(
     *           name="q",
     *           description="Keyword",
     *           paramType="query",
     *           required=false,
     *           type="string"
     *         ),
     *         @SWG\Parameter(
     *           name="status",
     *           description="Status, allow value : pending | published | deleted | draft",
     *           paramType="query",
     *           required=false,
     *           type="string"
     *         ),
     *         @SWG\Parameter(
     *           name="type",
     *           description="Allow value : news | data",
     *           paramType="query",
     *           required=false,
     *           type="string"
     *         ),
     *         @SWG\Parameter(
     *           name="format",
     *           description="Allow value : markdown | json",
     *           paramType="query",
     *           required=false,
     *           type="string"
     *         ),
     *         @SWG\Parameter(
     *           name="uid",
     *           description="User ID",
     *           paramType="query",
     *           required=false,
     *           type="integer"
     *         ),
     *         @SWG\Parameter(
     *           name="cid",
     *           description="Category ID, split multi by comma",
     *           paramType="query",
     *           required=false,
     *           type="string"
     *         ),
     *         @SWG\Parameter(
     *           name="importance",
     *           description="Importance 1-3, split multi by comma",
     *           paramType="query",
     *           required=false,
     *           type="string"
     *         ),
     *         @SWG\Parameter(
     *           name="limit",
     *           description="Limit max:100 | min:3; default is 25",
     *           paramType="query",
     *           required=false,
     *           type="integer"
     *         ),
     *         @SWG\Parameter(
     *           name="page",
     *           description="Page",
     *           paramType="query",
     *           required=false,
     *           type="integer"
     *         )
     *       )
     *     )
     *   )
     * )
     */
    public function indexAction()
    {
        $limit = $this->request->getQuery('limit', 'int', 25);
        $limit = $limit > 100 ?: $limit;
        $limit = $limit < 3 ?: $limit;
        //fixed order
        $query = array(
            'q' => $this->request->getQuery('q', 'string'),
            'status' => $this->request->getQuery('status', 'string'),
            'type' => $this->request->getQuery('type', 'string'),
            'codeType' => $this->request->getQuery('format', 'string'),
            'uid' => $this->request->getQuery('uid', 'int'),
            'cid' => $this->request->getQuery('cid'),
            'importance' => $this->request->getQuery('importance', 'string'),
            'order' => '-updated_at',
            'page' => $this->request->getQuery('page', 'int', 1),
        );


        $form = new Forms\FilterForm();
        $form->setValues($this->request->getQuery());

        $newsManager = new Models\NewsManager();
        $paginator = new \Eva\EvaEngine\Paginator(array(
            "builder" => $newsManager->findNews($query),
            "limit"=> $limit,
            "page" => $query['page']
        ));
        $paginator->setQuery($query);
        $pager = $paginator->getPaginate();

        $livenewsArray = array();
        if ($pager->items) {
            foreach ($pager->items as $key => $livenews) {
                $livenewsArray[] = $livenews->dump(NewsManager::$simpleDump);
            }
        }

        $data = array(
            'paginator' => $this->getApiPaginator($paginator),
            'results' => $livenewsArray,
        );
        return $this->response->setJsonContent($data);
    }

    /**
     *
     * @SWG\Api(
     *   path="/livenews/realtime",
     *   description="Livenews related api",
     *   produces="['application/json']",
     *   @SWG\Operations(
     *     @SWG\Operation(
     *       method="GET",
     *       summary="Get newest livenews for refresh",
     *       notes="Returns livenews list",
     *       @SWG\Parameters(
     *         @SWG\Parameter(
     *           name="min_updated",
     *           description="Min updated time",
     *           paramType="query",
     *           required=false,
     *           type="integer"
     *         ),
     *         @SWG\Parameter(
     *           name="cid",
     *           description="Category ID",
     *           paramType="query",
     *           required=false,
     *           type="string"
     *         ),
     *         @SWG\Parameter(
     *           name="limit",
     *           description="Limit max:100 | min:1; default is 3",
     *           paramType="query",
     *           required=false,
     *           type="integer"
     *         )
     *       )
     *     )
     *   )
     * )
     */
    public function realtimeAction()
    {
        $limit = $this->request->getQuery('limit', 'int', 3);
        $limit = $limit > 100 ?: $limit;
        $minUpdated = $this->request->getQuery('min_updated', 'int', 0);
        $cid = $this->request->getQuery('cid');
        $redis = $this->getDI()->getFastCache();
        $data = array();
        if ($minUpdated > 0) {
            //No limit when has min updated
            //TODO: should change cid as a filter
            $limit = 100;
            $data = $redis->zRangeByScore("livenews", $minUpdated, 999999999, array(
                'limit' => array(0, (int) $limit)
            ));
        } else {
            $data = $redis->zRange('livenews', (0 - $limit), -1);
        }
        rsort($data);
        $dataString = '{"paginator": null, "results": [' . implode(',', $data) . ']}';
        if ($cid) {
            $cidArray = is_array($cid) ? $cid : explode(',', $cid);
            $data = json_decode($dataString, true);
            $results = $data['results'];
            $data = array();
            foreach ($results as $key => $result) {
                if (!$result['categorySet']) {
                    continue;
                }
                $categorySet = explode(',', $result['categorySet']);
                if (array_intersect($categorySet, $cidArray)) {
                    $data[] = $result;
                    break;
                }
            }
            $dataString = json_encode(array(
                'paginator' => null,
                'results' => $data
            ));
        }
        return $this->response->setContent($dataString);
    }

    /**
    *
    * @SWG\Api(
        *   path="/livenews/{livenewsId}",
        *   description="Livenews related api",
        *   produces="['application/json']",
        *   @SWG\Operations(
            *     @SWG\Operation(
                *       method="GET",
                *       summary="Find livenews by ID",
     *       notes="Returns a livenews based on ID",
     *       @SWG\Parameters(
     *         @SWG\Parameter(
     *           name="livenewsId",
     *           description="ID of livenews",
     *           paramType="path",
     *           required=true,
     *           type="integer"
     *         )
     *       )
     *     )
     *   )
     * )
     */
    public function getAction()
    {
        $id = $this->dispatcher->getParam('id');
        $livenewsModel = new Models\NewsManager();
        $livenews = $livenewsModel->findFirst($id);
        if (!$livenews) {
            throw new Exception\ResourceNotFoundException('Request livenews not exist');
        }
        $livenews = $livenews->dump(Models\NewsManager::$defaultDump);
        return $this->response->setJsonContent($livenews);
    }

    /**
     *
     * @SWG\Api(
     *   path="/livenews/{livenewsId}",
     *   description="Livenews related api",
     *   produces="['application/json']",
     *   @SWG\Operations(
     *     @SWG\Operation(
     *       method="PUT",
     *       summary="Update livenews by ID",
     *       notes="Returns updated livenews",
     *       @SWG\Parameters(
     *         @SWG\Parameter(
     *           name="livenewsId",
     *           description="ID of livenews",
     *           paramType="path",
     *           required=true,
     *           type="integer"
     *         )
     *       ),
     *       @SWG\Parameters(
     *         @SWG\Parameter(
     *           name="livenewsData",
     *           description="Livenews info",
     *           paramType="body",
     *           required=true,
     *           type="string"
     *         )
     *       )
     *     )
     *   )
     * )
     */
    public function putAction()
    {
        $id = $this->dispatcher->getParam('id');
        $data = $this->request->getRawBody();
        if (!$data) {
            throw new Exception\InvalidArgumentException('No data input');
        }
        if (!$data = json_decode($data, true)) {
            throw new Exception\InvalidArgumentException('Data not able to decode as JSON');
        }
        $livenews = Models\NewsManager::findFirst($id);
        if (!$livenews) {
            throw new Exception\ResourceNotFoundException('Request livenews not exist');
        }
        $form = new Forms\NewsForm();
        $form->setModel($livenews);
        $form->addForm('text', 'Eva\EvaLivenews\Forms\TextForm');
        if (!$form->isFullValid($data)) {
            return $this->showInvalidMessagesAsJson($form);
        }
        try {
            $form->save('updateNews');
            $data = $livenews->dump(Models\NewsManager::$defaultDump);
            return $this->response->setJsonContent($data);
        } catch (\Exception $e) {
            return $this->showExceptionAsJson($e, $form->getModel()->getMessages());
        }
    }

     /**
     *
     * @SWG\Api(
     *   path="/livenews",
     *   description="Livenews related api",
     *   produces="['application/json']",
     *   @SWG\Operations(
     *     @SWG\Operation(
     *       method="POST",
     *       summary="Create new livenews",
     *       notes="Returns a livenews based on ID",
     *       @SWG\Parameters(
     *         @SWG\Parameter(
     *           name="livenews json",
     *           description="Livenews info",
     *           paramType="body",
     *           required=true,
     *           type="string"
     *         )
     *       )
     *     )
     *   )
     * )
     */
    public function postAction()
    {
        $data = $this->request->getRawBody();
        if (!$data) {
            throw new Exception\InvalidArgumentException('No data input');
        }
        if (!$data = json_decode($data, true)) {
            throw new Exception\InvalidArgumentException('Data not able to decode as JSON');
        }

        $form = new Forms\NewsForm();
        $livenews = new Models\NewsManager();
        $form->setModel($livenews);
        $form->addForm('text', 'Eva\EvaLivenews\Forms\TextForm');

        if (!$form->isFullValid($data)) {
            return $this->showInvalidMessagesAsJson($form);
        }

        try {
            $form->save('createNews');
            $data = $livenews->dump(Models\NewsManager::$defaultDump);
            return $this->response->setJsonContent($data);
        } catch (\Exception $e) {
            return $this->showExceptionAsJson($e, $form->getModel()->getMessages());
        }
    }

    /**
    *
     * @SWG\Api(
     *   path="/livenews/{livenewsId}",
     *   description="Livenews related api",
     *   produces="['application/json']",
     *   @SWG\Operations(
     *     @SWG\Operation(
     *       method="DELETE",
     *       summary="Delete livenews by ID",
     *       notes="Returns deleted livenews",
     *       @SWG\Parameters(
     *         @SWG\Parameter(
     *           name="livenewsId",
     *           description="ID of livenews",
     *           paramType="path",
     *           required=true,
     *           type="integer"
     *         )
     *       )
     *     )
     *   )
     * )
     */
    public function deleteAction()
    {
         $id = $this->dispatcher->getParam('id');
         $livenews = Models\NewsManager::findFirst($id);
        if (!$livenews) {
            throw new Exception\ResourceNotFoundException('Request livenews not exist');
        }
         $livenewsinfo = $livenews->dump(Models\NewsManager::$defaultDump);
        try {
            $livenews->removeNews($id);
            return $this->response->setJsonContent($livenewsinfo);
        } catch (\Exception $e) {
            return $this->showExceptionAsJson($e, $livenews->getMessages());
        }
    }
}
