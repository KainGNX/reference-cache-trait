# Reference Cache Trait for CakePHP 3.x

This trait is intended for use with CakePHP entity classes in caching commonly used reference tables such as cities, states, zip codes, or countries. Using the trait requires a few steps and some basic configuration.

The intended usage for this trait of for entities which would otherwise have to
repeatedly query data from reference tables, which is 
very slow due to adding extra queries for every entity created, and possibly be exponential.

This trait will cache entire reference tables based on the namespace of the entity using it, and 
then the name space of the entity being cached with a 'entities' array within.

The cache list will not be stored directly under its namespace to allow parallel
properties to be stored within that namespace if needed in the future.

## Usage
1. Override the Entity contructor that will use this trait
2. After the parent constructor callback, call `$this->initReferenceCache([])` with a definition list passed in
3. Make sure the definition list follows the pattern for `$this->referenceDefinition`, see comments
4. To get a list of properties from the cached, call `$this->getCachedEntities('DefinitionKey')` in the entity where needed
5. Make sure the 'DefinitionKey' matches a property structured element within `$this->referenceDefinition`

The reason a `DefinitionKey` is needed is to support multiple cached lists per entity.
This due to the fact that traits can not be extended and the entity that uses it
will share the same instances of methods and properties.

## Reference Cache Definition
Must be defined the constructor of entity using this trait.
Used to define the options for caching each table and its entities



#### Reference Cache Definition Example

REQUIRED :: Use the following pattern for each element of `ReferenceCacheTrait::cacheDefinition` array. After overriding the constructor of your entity where this will be used, do something similar to the following example. Multiple items may be added.

```PHP
    $this->initReferenceCache(
        'States' => [
            'tableRegistryAlias' => 'Cities',
            'entityNamespace' => 'App.Model.Entity.EntityName',
            'referenceProperty' => 'property_name',
            'keyField' => 'field_name_of_entity', 
            'conditions' => []
        ]
    );
```

#### Adding Reference Definitions Afterwards

Simply do the following, and it will be merged with existing definitions. Again, multiple items maybe added.

```PHP
    $this->addReferenceDefinition(
        'MoreStates' => [
            'tableRegistryAlias' => 'MoreCities',
            'entityNamespace' => 'App.Model.Entity.EntityName',
            'referenceProperty' => 'property_name',
            'keyField' => 'field_name_of_entity', 
            'conditions' => []
        ]
    );
```

##### Reference Cache Definition Properties

For sake of consistency and following the CakePHP convention, each cache definition should be keyed by the plural table name.

- `tableRegistryAlias`: CakePHP Table Alias of the Entity to cache
- `entityNamespace`: Array key used to store entities in cache under entity dotted namespace
- `referenceProperty`: The property of the entity used to pull from the cache, such as the foreign key, or field of choosing
- `keyField`: The field name value which return entites are keyed to
- `conditions`: Conditions on which to select the entities


Whichever entity is using a cache trait, the pathing to the cached sub entities will be
the parent entity namespace with slashes replaced with dots, and then a child array 
using the namespace of the child entity with slashes replaced with dots.

The `entityNamespace` could be derived from an instance of a table object, it is better
to passed it in directly so that a table does not have to be created for each entity
even when cached to look up and convert that value.

To avoid cache conflicts, and maintiain context of `conditions` when cached, all entites will be cached in a dotted namespace reflecting the parent class of the reference cache.

##### Example from the root of the Cache

`App.Model.Entity.ParentEntity' => 'App.Model.Entity.ChildEntity' => ['entities] => \App\Model\Entity\ChildEntity[]`

So for example States is the parent of Cities, and this would be the array path to the cached entities in the `\Cake\Cache\Cache`.
 
`App.Model.Entity.States => App.Model.Entity.Cities => entities`
