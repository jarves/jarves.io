<?php

namespace AppBundle\Controller;

use Jarves\Admin\FieldTypes\AbstractType;
use Jarves\Admin\FieldTypes\FieldTypes;
use Jarves\Configuration\Bundle;
use Jarves\Configuration\Configs;
use Jarves\Configuration\Field;
use Jarves\Configuration\Model;
use Jarves\Configuration\Object;
use Jarves\Extractor\ClassExtractor;
use Jarves\Jarves;
use Jarves\Model\Node;
use Jarves\PluginController;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class DocumentationController extends PluginController
{
    public function fieldDetailAction($className = null)
    {
        if (!$className) {
            return '';
        }

        /** @var Jarves $jarves */
        $jarves = clone $this->get('jarves');

        $fieldTypes = $jarves->getFieldTypes();

        $type = $fieldTypes->newType($className);

        $fieldDefinition = new Field(null, $jarves);
        $fieldDefinition->setId('<fieldName>');

        $pkField = new Field(null, $jarves);
        $pkField->setId('<idFieldName>');
        $pkField->setType('number');
        $pkField->setPrimaryKey(true);
        $pkField->setAutoIncrement(true);

        $type->setFieldDefinition($fieldDefinition);

        $bundle = new Bundle('AppBundle\\MyBundleBundle', $jarves);

        $object = new Object(null, $jarves);
        $object->setId('<objectName>');
        $object->addField($pkField);

        $foreignObject = new Object(null, $jarves);
        $foreignObject->setId('<foreignObjectName>');
        $foreignObject->addField($pkField);

        $bundle->addObject($foreignObject);
        $bundle->addObject($object);

        $fieldDefinition->setObject('MyBundleBundle/<foreignObjectName>');
        $object->addField($fieldDefinition);

        $configs = new Configs($jarves);
        $configs->addConfig($bundle);
        $type->bootRunTime($object, $configs);

        $addedObjects = [];
        foreach ($bundle->getObjects() as $bundleObject) {
            if ($bundleObject !== $object && $bundleObject !== $foreignObject) {
                $addedObjects[] = $bundleObject;
            }
        }

        return $this->renderPluginView('AppBundle:Field:detail.html.twig', [
            'type' => $type,
            'extractor' => ClassExtractor::create($type),
            'object' => $object,
            'configs' => $configs,
            'addedObjects' => $addedObjects
        ]);
    }

    public function configurationDetailAction($className = null)
    {
        if (!$className) {
            return '';
        }

        $config = $this->getConfig($className);

        return $this->renderPluginView('AppBundle:Configuration:detail.html.twig', [
            'config' => $config
        ]);
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

    public function fieldsNavigationAction(Node $parentNode)
    {
        $items = [];
        
        /** @var FieldTypes $fieldTypes */
        $fieldTypes = $this->get('jarves')->getFieldTypes();
        $pageStack = $this->get('jarves.page_stack');
        
        foreach ($fieldTypes->getTypes() as $id => $type) {
            
            $items[] = [
                'title' => $type->getName(),
                'path' => $pageStack->getNodeUrl($parentNode) . '/' . $id,
                'active' => false
            ];
        }

        // replace this example code with whatever you need
        return $this->render('AppBundle:Navigation:fields.html.twig', [
            'items' => $items
        ]);
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
                'active' => false
            ];

            $items[] = $item;
        }

        // replace this example code with whatever you need
        return $this->render('AppBundle:Navigation:configurations.html.twig', [
            'items' => $items
        ]);
    }
}
