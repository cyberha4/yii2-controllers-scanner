<?php
/**
 * Created by PhpStorm.
 * User: cyberha4
 * Date: 28.08.2017
 * Time: 23:02
 */

namespace cyberha4\scanner;

use common\components\simpledebugger\Debugger;
use common\models\User;
use ReflectionClass;
use yii\base\Action;
use yii\base\BaseObject;
use yii\base\Module;
use yii\base\Object;
use yii\helpers\ArrayHelper;
use yii\helpers\FileHelper;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\web\Controller;

class Scanner extends BaseObject
{
    public function init()
    {
        parent::init();
        $authManager = \Yii::$app->authManager;

        $role = $authManager->getRole('test');
//        $authManager->addChild($role, )
    }


    public function scanApplication(Module $module, array &$urls = []): array
    {
        static $count = 0;

        $module = is_object($module) ? $module : \Yii::createObject($module);

        foreach ($module->controllerMap as $id => $controllerClsnm) {
            /** @var Controller $controller */
            $controller = \Yii::createObject($controllerClsnm, [$id, $module]);
            $urls[$module->uniqueId][$controller->id . 'ControllerFromMap'] = $this->getRoutesFromController($controller);
        }


        $controllerNamespace = $module->controllerNamespace;

        $pathToControllers = \Yii::getAlias('@' . str_replace('\\', '/', $controllerNamespace));
        $paths = FileHelper::findFiles(
            $pathToControllers,
            ['recursive' => true]
        );


        foreach ($paths as $path) {
            $relationPath = explode($pathToControllers, $path)[1];
            $className = $module->controllerNamespace . str_replace('/', '\\', $relationPath);
            $className = pathinfo($className, PATHINFO_FILENAME);


            if (is_subclass_of($className, 'yii\base\Controller') && !(new ReflectionClass($className))->isAbstract()) {
                $controllerId = substr($relationPath, 1, strrpos($relationPath, 'Controller') - 1);
                /** @var Controller $controller */
                $controller = \Yii::createObject($className, [$controllerId, $module]);

                $urls[$module->uniqueId][$controller->id . 'Controller'] = $this->getRoutesFromController($controller);
            }

        }

        $allInnerModules = $module->getModules();

        /** @var Module $innerModule */
        foreach ($allInnerModules as $id => $innerModule) {
            $innerModule = $module->getModule($id);
            $this->scanApplication($innerModule, $urls);

        }

        return $urls;
    }

    private function getRoutesFromController(Controller $controller): array
    {
        $conrollerRoutes = [];

        $methods = (new ReflectionClass($controller::className()))->getMethods();

        /** @var \ReflectionMethod $method */
        foreach ($methods as $method) {
            if (strpos($method->name, 'action') === 0) {
                $end = '';
                if ($method->class !== $controller::className()) {
                    $end = 'inherit';
                }

                if ($method->name === 'actions') {
                    continue;
                }

                $actionId = substr($method->name, strlen('action'));
                $conrollerRoutes[] = $this->getRouteFromAction($controller, $actionId) . ' ' . $end;
            }
        }

        if(!empty($controller->actions())){
            foreach (array_keys($controller->actions()) as $actionId){
                $conrollerRoutes[] = $this->getRouteFromAction($controller, $actionId);
            }
        }

        return $conrollerRoutes;

    }

    private function getRouteFromAction(Controller $controller, $actionId): string
    {
        $route = '/' . strtolower(
                $controller->getRoute() . '/' .
                $actionId
            );

        return Html::a($route, [$route]) ;
    }


}