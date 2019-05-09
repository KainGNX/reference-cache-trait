<?php
/**
 * Reference Cache Trait
 * 
 * Used for caching commonly used entities which may or may not be associated with a table. 
 * 
 * @author Jason Horvath <jason.horvath@greaterdevelopment.net>
 * @license GPL v3 Copyright: 2018
 * @version 1.0.0
 */
namespace App\Model\Entity\Traits;

use Cake\Cache\Cache;
use Cake\Error\BaseErrorHandler;

use Cake\ORM\TableRegistry;

trait ReferenceCacheTrait {
    
    /**
     * Definition and mapping of how entities will be cached and referenced
     * 
     * @var array $referenceDefinition
     */
     protected $referenceDefinition = [];
    
    /**
     * Reference Store
     * 
     * While Entity is in memory:
     * 1.) Read from cache is stored here directly to memory
     * 2.) Additions/alterations to be made are written here
     * 3.) Once all operations are complete, write back to the cache for the next entity iteration
     * 
     * This saves on having to repeatedly read/write the \Cake\Cache\Cache with unnescessary operations
     * 
     * @var array $referenceStore
     */
    protected $referenceStore = [];

    /**
     * Init Reference Cache List
     * Defines the cache list definitions and bootstraps the cache for reading and writing
     * 
     * @param array $referenceDefinition
     * @return void
     */
    public function initReferenceCache(array $referenceDefinition)
    {
        $this->addReferenceDefinition($referenceDefinition);
        $this->bootstrapReferenceCache();
    }

    /**
     * Add Reference Definition
     * 
     * @param array $referenceDefinition
     * @return void
     */
    public function addReferenceDefinition(array $referenceDefinition)
    {
        $this->referenceDefinition = array_merge($this->referenceDefinition, $referenceDefinition);
    }

    /**
     * Bootstrap Reference Cache
     * Read from the cache and write it to a property to be modified or referenced
     * 
     * @return void
     */
    public function bootstrapReferenceCache()
    {
        $cacheNamespaceValue = Cache::read($this->getCacheNamespace());
        $this->referenceStore = (is_array($cacheNamespaceValue)) ? $cacheNamespaceValue : [] ;

        foreach($this->referenceDefinition as $definitionKey => $definitionNotUsed) {
            $entityNamespace = $this->getEntityNamespace($definitionKey);
            if(!array_key_exists($entityNamespace, $this->referenceStore)) {

                $this->referenceStore[$entityNamespace] = $this->getReferenceScaffold();
                $this->referenceStore[$entityNamespace]['entities'] = $this->getReferenceCacheEntities($definitionKey);
                $this->writeReferenceStore();

            }
        }
    }

    /**
     * Write Reference Store
     * Save toe the cache under the current entity namespace using this trait
     * 
     * @return void
     */
    public function writeReferenceStore()
    {
        Cache::write($this->getCacheNamespace(), $this->referenceStore);
    }

    /**
     * Get Cache List Entities
     * 
     * @param string $definitionKey
     * @return array $entities
     */
    public function getReferenceCacheEntities(string $definitionKey)
    {
        $keyField = $this->getKeyField($definitionKey);
        $table = $this->getReferenceCacheTable($definitionKey);
        $whereCondition = $this->getCondition('where', $definitionKey);
        
        $entities = $table->find('list', [
                'keyField' => $keyField,
                'valueField' => function ($entity) {
                    return $entity;
                }
            ])
            ->where($whereCondition)
            ->toArray();

        return $entities;     
    }

    /**
     * Get Key Field
     * The keyField is the field name which that value will be used to key entities to in the list
     * 
     * @param string $definitionKey
     */
    public function getKeyField(string $definitionKey)
    {
        return $this->referenceDefinition[$definitionKey]['keyField'];
    }
    
    /**
     * Get Condition
     * Based on the key defined under conditions with the referenceDefinition
     * 
     * @param string $conditionType
     * @param string $definitionKey
     */
    public function getCondition(string $conditionType, string $definitionKey)
    {
        return ($this->referenceDefinition[$definitionKey]['conditions'][$conditionType] ?? []);
    }

    /**
     * Get Reference Scaffold
     * This is the structure of a new cache list before being populated.
     * The sturcutre is intended to allow parallel properties related directly
     * to the entities being cached
     *  
     * @return array
     */
    public function getReferenceScaffold()
    {
        return [
            'entities' => [],
        ];
    }

    /**
     * Get Cached Entities
     * 
     * @param string $definitionKey
     * @return array Entity[]
     */
    public function getCachedEntities(string $definitionKey)
    {
        $cachedEntities = [];
        $referenceValues = $this->getReferencePropertyValue($definitionKey);
        $entityNamespace = $this->getEntityNamespace($definitionKey);
        foreach($referenceValues as $key) {
            $cachedEntities[] = ($this->referenceStore[$entityNamespace]['entities'][$key] ?? null);
        }
        return $cachedEntities;
    }

    /**
     * Get Reference Property Value
     * Returns the value of the entity property using this trait defined under a cache definition
     * 
     * @param string $definitionKey
     * @return mixed
     */
    public function getReferencePropertyValue(string $definitionKey)
    {
        $referenceProperty =  $this->referenceDefinition[$definitionKey]['referenceProperty'];
        $propertyValue = $this->{$referenceProperty};
        
        if(is_string($propertyValue)) {
            return [$propertyValue];
        }
        return ($propertyValue ?? []);
    }

    /**
     * Get Reference Cache Table
     * Returns a Table object base don the alias under a definition
     * 
     * @param string $definitionKey
     * @return Table
     */
    public function getReferenceCacheTable(string $definitionKey)
    {
        $tableRegistryAlias = $this->getTableRegistryAlias($definitionKey);
        return TableRegistry::get($tableRegistryAlias);
    }

    /**
     * Get Entity Namespace
     * The index value under with a specific cache list will be stored
     * 
     * @param string $definitionKey
     */
    public function getEntityNamespace(string $definitionKey)
    {
        return $this->referenceDefinition[$definitionKey]['entityNamespace'];
    }

    /**
     * Get Table Registry Alias
     * 
     * @param string $definitionKey
     * @return mixed
     * @throws BaseErrorHandler
     */
    public function getTableRegistryAlias(string $definitionKey)
    {
        try {
            if(empty($this->referenceDefinition[$definitionKey]['tableRegistryAlias'])) {
                throw new BaseErrorHandler('Missing tableRegistryAlias in ReferenceCacheTrait::referenceDefinition[] for Definition: ' . $definitionKey);
            }
            return $this->referenceDefinition[$definitionKey]['tableRegistryAlias'];
        } catch (BaseErrorHandler $e) {
            echo $e->getMessage();
        }
        return false;
    }

    /**
     * Get Cache Namespace
     * Converts the object namespace of the current entity to use dots
     * The entire scope of an entities caches lists will be stored under this key
     * 
     * @return string 
     */
    public function getCacheNamespace()
    {
        return str_replace('\\', '.', get_class($this));
    }

}
