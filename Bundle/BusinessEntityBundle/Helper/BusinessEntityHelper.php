<?php
namespace Victoire\Bundle\BusinessEntityBundle\Helper;

use Doctrine\ORM\EntityManager;
use Victoire\Bundle\BusinessEntityBundle\Entity\BusinessEntity;
use Victoire\Bundle\BusinessEntityBundle\Reader\BusinessEntityCacheReader;
use Victoire\Bundle\BusinessEntityPageBundle\Entity\BusinessEntityPagePattern;
use Victoire\Bundle\WidgetBundle\Entity\Widget;

/**
 * The BusinessEntityHelper
 *
 * ref: victoire_core.helper.business_entity_helper
 */
class BusinessEntityHelper
{
    protected $reader;
    protected $entityManager;
    protected $businessEntities;

    /**
     * Constructor
     * @param BusinessEntityCacheReader $reader
     * @param EntityManager             $entityManager
     */
    public function __construct(BusinessEntityCacheReader $reader, EntityManager $entityManager)
    {
        $this->reader = $reader;
        $this->entityManager = $entityManager;
    }

    /**
     * Get a business entity by its id
     * @param string $id
     *
     * @throws \Exception
     *
     * @return BusinessEntity
     */
    public function findById($id)
    {
        $businessEntity = $this->reader->findById($id);
        if ($businessEntity === null) {
            throw new \Exception("<<".$id. ">> does not seems to be a valid BusinessEntity");
        }

        return $businessEntity;
    }

    /**
     * Get all business entities
     *
     * @return BusinessEntity
     */
    public function getBusinessEntities()
    {
        return $this->reader->getBusinessClasses();
    }

    /**
     * this method get annotated business classes (from cache if enabled)
     * @param Widget $widget
     *
     * @return array $businessClasses
     **/
    public function getBusinessClassesForWidget(Widget $widget)
    {
        return $this->reader->getBusinessClassesForWidget($widget);
    }

    /**
     * Get a business entity
     * @param Entity $entity
     *
     * @return BusinessEntity
     */
    public function findByEntityInstance($entity)
    {
        $businessEntity = null;
        $class = new \ReflectionClass($entity);
        while (!$businessEntity && $class && $class->name != null) {
            $businessEntity = $this->findByEntityClassname($class->name);
            $class = $class->getParentClass();
        }

        return $businessEntity;
    }

    /**
     * Get a business entity by classname
     *
     * @param string $classname
     *
     * @return BusinessEntity
     */
    public function findByEntityClassname($classname)
    {
        //get all the business entities
        $businessEntities = $this->getBusinessEntities();

        //the result
        $businessEntity = null;

        //parse the business entities
        foreach ($businessEntities as $tempBusinessEntity) {
            //look for the same id
            if ($tempBusinessEntity->getClass() === $classname) {
                $businessEntity = $tempBusinessEntity;
                //business entity was found, there is no need to continue
                continue;
            }
        }

        return $businessEntity;
    }

    /**
     * Find a entity by the business entity and the attributeValue
     * @param BusinessEntity $businessEntity
     * @param string         $attributeName
     * @param string         $attributeValue
     *
     * @return Entity
     */
    public function findEntityByBusinessEntityAndAttribute(BusinessEntity $businessEntity, $attributeName, $attributeValue)
    {
        //retrieve the class of the business entity
        $class = $businessEntity->getClass();

        //get the repository
        $repo = $this->entityManager->getRepository($class);

        $functionName = 'findOneBy'.ucfirst($attributeName);

        //get the entity
        $entity = call_user_func(array($repo, $functionName), $attributeValue);

        return $entity;
    }

    /**
     * Get the entity from the page and the id given
     *
     * @param BusinessEntityPagePattern $page             The page
     * @param string                    $entityIdentifier The identifier for the business entity
     * @param string                    $attributeName    The name of the attribute used to identify an entity
     *
     * @throws \Exception
     *
     * @return The entity
     */
    public function getEntityByPageAndBusinessIdentifier(BusinessEntityPagePattern $page, $entityIdentifier, $attributeName)
    {
        $entity = null;

        $businessEntityName = $page->getBusinessEntityName();

        $businessEntity = $this->findById($businessEntityName);

        //test the result
        if ($businessEntity === null) {
            throw new \Exception('The business entity ['.$businessEntityName.'] was not found.');
        }

        $entity = $this->findEntityByBusinessEntityAndAttribute($businessEntity, $attributeName, $entityIdentifier);

        //test the result
        if ($entity === null) {
            throw new \Exception('The entity ['.$entityIdentifier.'] was not found.');
        }

        return $entity;
    }

    public function getByBusinessEntityAndId(BusinessEntity $businessEntity, $id)
    {
        return $this->em->getRepository($businessEntity->getClass())->findOneById($id);
    }
}
