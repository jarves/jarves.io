<?php

namespace AppBundle\Controller;

use AppBundle\Documentation\NelmioHtmlFormatter;
use Jarves\Admin\AdminAssets;
use Jarves\Admin\FieldTypes\AbstractType;
use Jarves\Admin\FieldTypes\FieldTypes;
use Jarves\Configuration\Bundle;
use Jarves\Configuration\Configs;
use Jarves\Configuration\Field;
use Jarves\Configuration\Model;
use Jarves\Configuration\Object;
use Jarves\Extractor\ClassExtractor;
use Jarves\Formatter\ApiDocFormatter;
use Jarves\Jarves;
use Jarves\Model\Node;
use Jarves\PluginController;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerNameParser;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Yaml\Dumper;

class DocumentationController extends PluginController
{
    protected function getCache($id)
    {
        return $this->get('jarves.cache.cacher')->getDistributedCache($id);
    }

    protected function setCache($id, $cache)
    {
        return $this->get('jarves.cache.cacher')->setDistributedCache($id, $cache);
    }

    public function fieldDetailAction($className = null)
    {
        if ($cache = $this->getCache($cacheKey = 'jarves/documentation/field/detail/' . $className)) {
            return $cache;
        }

        if (!$className) {
            return null;
        }

        /** @var Jarves $jarves */
        $jarves = $this->get('jarves');

        $fieldTypes = $jarves->getFieldTypes();
        $type = $jarves->getConfigs()->getFieldType($className);
        $typeInstance = null;
        $object = null;
        $configs = null;
        $addedObjects = null;

//        $adminAssets = new AdminAssets($this->jarves, $this->pageStack, $this->acl);
//        $adminAssets->addMainResources();
//        $pageStack->getPageResponse()->addJsFile($type->getJavascript());

        if (!$type->isUserInterfaceOnly()) {
            $typeInstance = $fieldTypes->newType($className);

            $fieldDefinition = new Field(null, $jarves);
            $fieldDefinition->setId('<fieldName>');

            $pkField = new Field(null, $jarves);
            $pkField->setId('<idFieldName>');
            $pkField->setType('number');
            $pkField->setPrimaryKey(true);
            $pkField->setAutoIncrement(true);

            $typeInstance->setFieldDefinition($fieldDefinition);

            $bundle = new Bundle('AppBundle\\AppBundle', $jarves);

            $object = new Object(null, $jarves);
            $object->setId('<objectName>');
            $object->addField($pkField);

            $foreignObject = new Object(null, $jarves);
            $foreignObject->setId('<foreignObjectName>');
            $foreignObject->addField($pkField);

            $bundle->addObject($foreignObject);
            $bundle->addObject($object);

            $fieldDefinition->setObject('AppBundle/<foreignObjectName>');
            $object->addField($fieldDefinition);

            $configs = new Configs($jarves);
            $configs->addConfig($bundle);
            $typeInstance->bootRunTime($object, $configs);

            $addedObjects = [];
            foreach ($bundle->getObjects() as $bundleObject) {
                if ($bundleObject !== $object && $bundleObject !== $foreignObject) {
                    $addedObjects[] = $bundleObject;
                }
            }
        }

        $result = $this->renderPluginView('AppBundle:Field:detail.html.twig', [
            'typeConfig' => $type,
            'type' => $typeInstance,
            'extractor' => ClassExtractor::create($typeInstance),
            'object' => $object,
            'configs' => $configs,
            'addedObjects' => $addedObjects
        ]);

        $this->setCache($cacheKey, $result);
        return $result;
    }

    public function contentTypeDetailAction($className = null)
    {
        if ($cache = $this->getCache($cacheKey = 'jarves/documentation/content-type/detail/' . $className)) {
            return $cache;
        }

        if (!$className) {
            return null;
        }

        $jarves = $this->get('jarves');

        $contentType = $jarves->getConfigs()->getContentType($className);
        $extractor = null;
        if ($contentType->getId() !== 'stopper') {
            $extractor = ClassExtractor::create($this->get($contentType->getService()));
        }

        $result = $this->renderPluginView('AppBundle:ContentType:detail.html.twig', [
            'contentType' => $contentType,
            'extractor' => $extractor,
        ]);
        $this->setCache($cacheKey, $result);
        return $result;
    }

    public function objectsDetailAction($objectKey = null)
    {
        $pageStack = $this->get('jarves.page_stack');
        $pageStack->getPageResponse()->loadAssetFile('@AppBundle/css/api-doc.scss');
        $pageStack->getPageResponse()->loadAssetFileAtBottom('@AppBundle/js/api-doc.js');

        if ($cache = $this->getCache($cacheKey = 'jarves/documentation/objects/detail/' . $objectKey)) {
            return $cache;
        }

        if (!$objectKey) {
            return null;
        }

        $jarves = $this->get('jarves');

        $object = $jarves->getConfigs()->getObject($objectKey);

        $commentExtractor = new \Nelmio\ApiDocBundle\Util\DocCommentExtractor;
        $controllerNameParser = new ControllerNameParser($this->get('kernel'));

        $handlers = [
            new \Nelmio\ApiDocBundle\Extractor\Handler\FosRestHandler,
            new \Nelmio\ApiDocBundle\Extractor\Handler\JmsSecurityExtraHandler,
            new \Nelmio\ApiDocBundle\Extractor\Handler\SensioFrameworkExtraHandler,
            new \Jarves\Extractor\Handler\ObjectCrudHandler($this->get('jarves'), $this->get('jarves.objects')),
//            new \Nelmio\ApiDocBundle\Extractor\Handler\PhpDocHandler($commentExtractor),
        ];

        $extractor = new \Nelmio\ApiDocBundle\Extractor\ApiDocExtractor(
            $this->get('service_container'),
            $this->get('router'),
            $this->get('annotation_reader'),
            $commentExtractor,
            $controllerNameParser,
            $handlers,
            []
        );

        $routes = [];

        foreach ($this->get('router')->getRouteCollection()->all() as $route) {
            if ($route->hasDefault('_jarves_object') && $route->getDefault('_jarves_object') === $object->getKey()) {
                $routes[] = $route;
            }
        }

        $extractedDoc = $extractor->extractAnnotations($routes);

        $formatter = new NelmioHtmlFormatter();
        $formatter->setTemplatingEngine($this->get('templating'));

        $apiDoc = $formatter
            ->format($extractedDoc);

        $result = $this->renderPluginView('AppBundle:Objects:detail.html.twig', [
            'objectConfig' => $object,
            'apiDoc' => $apiDoc
        ]);
        $this->setCache($cacheKey, $result);
        return $result;
    }

    public function restApiAction()
    {
        $pageStack = $this->get('jarves.page_stack');
        $pageStack->getPageResponse()->loadAssetFile('@AppBundle/css/api-doc.scss');
        $pageStack->getPageResponse()->loadAssetFileAtBottom('@AppBundle/js/api-doc.js');

        if ($cache = $this->getCache($cacheKey = 'jarves/documentation/rest-api')) {
            return $cache;
        }

        $handlers = [
            new \Nelmio\ApiDocBundle\Extractor\Handler\FosRestHandler,
            new \Nelmio\ApiDocBundle\Extractor\Handler\JmsSecurityExtraHandler,
            new \Nelmio\ApiDocBundle\Extractor\Handler\SensioFrameworkExtraHandler,
            new \Jarves\Extractor\Handler\ObjectCrudHandler($this->get('jarves'), $this->get('jarves.objects')),
//            new \Nelmio\ApiDocBundle\Extractor\Handler\PhpDocHandler($commentExtractor),
        ];

        $commentExtractor = new \Nelmio\ApiDocBundle\Util\DocCommentExtractor;
        $controllerNameParser = new ControllerNameParser($this->get('kernel'));

        $extractor = new \Nelmio\ApiDocBundle\Extractor\ApiDocExtractor(
            $this->get('service_container'),
            $this->get('router'),
            $this->get('annotation_reader'),
            $commentExtractor,
            $controllerNameParser,
            $handlers,
            []
        );

        $routes = [];

        foreach ($this->get('router')->getRouteCollection()->all() as $route) {
            if (0 == strpos($route->getPath(), '/jarves') && !$route->hasDefault('_jarves_object')) {
                $routes[] = $route;
            }
        }

        $extractedDoc = $extractor->extractAnnotations($routes);

        $formatter = new NelmioHtmlFormatter();
        $formatter->setTemplatingEngine($this->get('templating'));

        $apiDoc = $formatter
            ->format($extractedDoc);

        $result = "<div class=\"inline-nelmio-api-doc\">$apiDoc</div>";

        $this->setCache($cacheKey, $result);
        return $result;
    }

    public function configurationDetailAction($className = null)
    {
        if ($cache = $this->getCache($cacheKey = 'jarves/documentation/configuration/detail/' . $className)) {
            return $cache;
        }

        if (!$className) {
            return null;
        }

        $config = $this->getConfig($className);


        $result = $this->renderPluginView('AppBundle:Configuration:detail.html.twig', [
            'config' => $config,
        ]);
        $this->setCache($cacheKey, $result);
        return $result;
    }

    /**
     * @param string $baseClass
     *
     * @return Model|null
     */
    protected function getConfig($baseClass)
    {
        $baseClass = ucfirst($baseClass);

        $class = 'Jarves\\Configuration\\' . $baseClass;
        $reflection = new \ReflectionClass($class);

        if (!$reflection->isSubclassOf('Jarves\\Configuration\\Model')) {
            return null;
        }

        if ('SimpleKeyModel' == $baseClass || 'SimpleModel' == $baseClass) {
            return null;
        }

        $jarves = $this->get('jarves');

        /** @var Model $config */
        if ($baseClass === 'Bundle') {
            $config = new $class('BundleName', $jarves);
        } else {
            $config = new $class(null, $jarves);
        }

        return $config;
    }

    public function fieldsNavigationAction(Request $request, Node $parentNode)
    {

        $items = [];

        $jarves = $this->get('jarves');

        /** @var FieldTypes $fieldTypes */
        $fieldTypes = $this->get('jarves')->getFieldTypes();
        $pageStack = $this->get('jarves.page_stack');

        $affix = trim($pageStack->getCurrentUrlAffix(), '/');

        foreach ($jarves->getConfigs()->getFieldTypes() as $type) {
            $items[] = [
                'title' => $type->getLabel(),
                'id' => $type->getId(),
                'path' => $pageStack->getNodeUrl($parentNode) . '/' . $type->getId(),
                'active' => $affix === $type->getId() && $pageStack->getCurrentPage()->getId() === $parentNode->getId()
            ];
        }

        $result = $this->render('AppBundle:Navigation:fields.html.twig', [
            'items' => $items
        ]);
        return $result;
    }

    public function contentTypesNavigationAction(Node $parentNode)
    {
        $items = [];

        $jarves = $this->get('jarves');
        $pageStack = $this->get('jarves.page_stack');

        $affix = trim($pageStack->getCurrentUrlAffix(), '/');
        foreach ($jarves->getConfigs()->getContentTypes() as $type) {

            $items[] = [
                'title' => $type->getLabel(),
                'path' => $pageStack->getNodeUrl($parentNode) . '/' . $type->getId(),
                'active' => $affix === $type->getId() && $pageStack->getCurrentPage()->getId() === $parentNode->getId()
            ];
        }

        $result = $this->render('AppBundle:Navigation:fields.html.twig', [
            'items' => $items
        ]);
        return $result;
    }

    public function configurationNavigationAction(Node $parentNode)
    {
        $reflection = new \ReflectionClass('Jarves\JarvesBundle');
        $jarvesDir = dirname($reflection->getFileName());
        $jarvesConfigurationFolder = $jarvesDir . '/Configuration/';

        $files = Finder::create()
            ->in($jarvesConfigurationFolder)
            ->files();

        $pageStack = $this->get('jarves.page_stack');

        $items = [];
        $affix = trim($pageStack->getCurrentUrlAffix(), '/');

        /** @var SplFileInfo $file */
        foreach ($files as $file) {
            $baseClass = substr($file->getFilename(), 0, -4);

            $config = $this->getConfig($baseClass);
            if (!$config) {
                continue;
            }

            $item = [
                'title' => $baseClass,
                'rootName' => $config->getRootName(),
                'path' => $pageStack->getNodeUrl($parentNode) . '/' . lcfirst($baseClass),
                'active' => $affix === lcfirst($baseClass) && $pageStack->getCurrentPage()->getId() === $parentNode->getId()
            ];

            $items[] = $item;
        }

        $result = $this->render('AppBundle:Navigation:configurations.html.twig', [
            'items' => $items
        ]);
        return $result;
    }

    public function objectsNavigationAction(Node $parentNode)
    {
        $items = [];

        $jarves = $this->get('jarves');
        $pageStack = $this->get('jarves.page_stack');

        $affix = trim($pageStack->getCurrentUrlAffix(), '/');
        foreach ($jarves->getConfigs()->getObjects() as $object) {

            $items[] = [
                'title' => $object->getKey(),
                'path' => $pageStack->getNodeUrl($parentNode) . '/' . $object->getKey(),
                'active' => $affix === $object->getKey() && $pageStack->getCurrentPage()->getId() === $parentNode->getId()
            ];
        }

        $result = $this->render('AppBundle:Navigation:fields.html.twig', [
            'items' => $items
        ]);
        return $result;
    }
}
