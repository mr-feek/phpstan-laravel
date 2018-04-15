<?php

namespace Weebly\PHPStan\Laravel;

use Illuminate\Http\Resources\Json\Resource;
use PHPStan\Broker\Broker;
use PHPStan\Reflection\Annotations\AnnotationsPropertiesClassReflectionExtension;
use PHPStan\Reflection\BrokerAwareExtension;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\PropertiesClassReflectionExtension;
use PHPStan\Reflection\PropertyReflection;

class ResourceExtension implements PropertiesClassReflectionExtension, BrokerAwareExtension
{
    /**
     * @var \PHPStan\Reflection\PropertyReflection[]
     */
    private $properties = [];

    private $classNameToResourceTypehintReflection = [];

    /**
     * @var Broker
     */
    private $broker;

    /**
     * @var AnnotationsPropertiesClassReflectionExtension
     */
    private $annotationsPropertiesClassReflectionExtension;

    public function __construct(AnnotationsPropertiesClassReflectionExtension $annotationsPropertiesClassReflectionExtension)
    {
        $this->annotationsPropertiesClassReflectionExtension = $annotationsPropertiesClassReflectionExtension;
    }

    public function hasProperty(ClassReflection $classReflection, string $propertyName): bool
    {
        if (!$classReflection->isSubclassOf(Resource::class)) {
            return false;
        }

        $className = $classReflection->getName();

        if (!isset($this->classNameToResourceTypehintReflection[$className])) {
            $nativeClassReflection = $classReflection->getNativeReflection();
            $resourceTypehint = $nativeClassReflection->getConstructor()->getParameters()[0]->getClass();

            if (!$resourceTypehint) {
                return false;
            }

            $this->classNameToResourceTypehintReflection[$className] = $this->broker->getClass($resourceTypehint->getName());;
        }

        $resourceTypehintClassReflection = $this->classNameToResourceTypehintReflection[$className];

        if (!isset($this->properties[$className])) {
            $this->properties[$className] = $this->createProperties($resourceTypehintClassReflection);
        }

        if (isset($this->properties[$className][$propertyName])) {
            return true;
        };

        return $this->annotationsPropertiesClassReflectionExtension->hasProperty($resourceTypehintClassReflection, $propertyName);
    }

    public function getProperty(ClassReflection $classReflection, string $propertyName): PropertyReflection
    {
        if (isset($this->properties[$classReflection->getName()]) && isset($this->properties[$classReflection->getName()][$propertyName])) {
            return $this->properties[$classReflection->getName()][$propertyName];
        }

        return $this->annotationsPropertiesClassReflectionExtension->getProperty($this->classNameToResourceTypehintReflection[$classReflection->getName()], $propertyName);
    }

    public function setBroker(Broker $broker)
    {
        $this->broker = $broker;
    }

    private function createProperties(ClassReflection $resourceTypehintClassReflection)
    {
        $properties = [];
        foreach ($resourceTypehintClassReflection->getNativeReflection()->getProperties(\ReflectionMethod::IS_PUBLIC) as $property) {
            $properties[$property->getName()] = $property;
        }

        return $properties;
    }
}
