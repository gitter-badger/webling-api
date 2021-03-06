<?php

namespace Terminal42\WeblingApi;

use Terminal42\WeblingApi\Entity\EntityInterface;

class EntityManager
{
    const API_VERSION    = 1;

    /**
     * @var ClientInterface
     */
    private $client;

    /**
     * @var EntityFactoryInterface
     */
    private $factory;

    /**
     * @var array
     */
    private $entities = [];

    /**
     * Constructor.
     *
     * @param ClientInterface        $client  The HTTP client to make requests to the server.
     * @param EntityFactoryInterface $factory A factory capable of creating entities.
     */
    public function __construct(ClientInterface $client, EntityFactoryInterface $factory)
    {
        $this->client  = $client;
        $this->factory = $factory;
    }

    /**
     * Returns the configuration of entities and other account information.
     * ATTENTION: This call is currently undocumented and could be removed in any update to the API.
     *
     * @internal
     */
    public function getConfig()
    {
        static $config;

        if (null === $config) {
            $config = $this->client->get('/config');
        }

        return $config;
    }

    /**
     * Gets the factory instance used by this entity manager.
     *
     * @return EntityFactoryInterface
     */
    public function getFactory()
    {
        return $this->factory;
    }

    /**
     * Finds all entities for a given type.
     *
     * @param string $type      The entity type
     * @param string $filter    Filter the result by given keyword(s)
     * @param string $sort      Sort the passed property
     * @param string $direction Sort order (see RepositoryInterface constants)
     *
     * @return EntityList
     */
    public function findAll($type, $filter = '', $sort = '', $direction = '')
    {
        $query = [
            'filter'    => $filter,
            'sort'      => $sort,
            'direction' => $direction
        ];

        $data = $this->client->get("/$type", array_filter($query));

        return new EntityList($type, $data['objects'], $this);
    }

    /**
     * Find an entity by ID of given type.
     *
     * @param string $type The entity type
     * @param int    $id   The entity ID
     *
     * @return mixed
     */
    public function find($type, $id)
    {
        if (!isset($this->entities[$type][$id])) {
            $data   = $this->client->get("/$type/$id");
            $entity = $this->factory->create($this, $data, $id);

            $this->entities[$type][$id] = $entity;
        }

        return $this->entities[$type][$id];
    }

    /**
     * Add or update the entity in Webling.
     *
     * @param EntityInterface $entity
     *
     * @throws \InvalidArgumentException If the entity is readonly
     */
    public function persist(EntityInterface $entity)
    {
        if ($entity->isReadonly()) {
            throw new \InvalidArgumentException('The entity must not be readonly.');
        }

        if (null === $entity->getId()) {
            $this->create($entity);
        } else {
            $this->update($entity);
        }

        $this->entities[$entity->getType()][$entity->getId()] = $entity;
    }

    /**
     * Delete entity from Webling.
     *
     * @param EntityInterface $entity
     *
     * @throws \UnexpectedValueException If the entity does not have an ID
     * @throws \InvalidArgumentException If the entity is readonly
     */
    public function remove(EntityInterface $entity)
    {
        $this->validateHasId($entity);

        if ($entity->isReadonly()) {
            throw new \InvalidArgumentException('The entity must not be readonly.');
        }

        $id   = $entity->getId();
        $type = $entity->getType();

        $this->client->delete("/$type/$id");

        unset($this->entities[$type][$id]);
        $entity->unsetId();
    }

    /**
     * Creates an entity in Webling.
     *
     * @param EntityInterface $entity
     */
    private function create(EntityInterface $entity)
    {
        $type = $entity->getType();
        $data = $entity->serialize();

        $result = $this->client->post("/$type", $data);

        $entity->setId($result);
    }

    /**
     * Updates an entity in Webling.
     *
     * @param EntityInterface $entity
     */
    private function update(EntityInterface $entity)
    {
        $id   = $entity->getId();
        $type = $entity->getType();
        $data = $entity->serialize();

        $this->client->put("/$type/$id", $data);
    }

    /**
     * Throws exception if entity does not have an ID.
     *
     * @param EntityInterface $entity
     *
     * @throws \UnexpectedValueException
     */
    private function validateHasId(EntityInterface $entity)
    {
        if (null === $entity->getId()) {
            throw new \UnexpectedValueException('The entity must have an ID.');
        }
    }

    /**
     * Creates a new entity manager for the given Webling account.
     *
     * @param string $subdomain Your Webling subdomain.
     * @param string $apiKey    Your Webling API key.
     *
     * @return static
     */
    public static function createForAccount($subdomain, $apiKey)
    {
        return new static(new Client($subdomain, $apiKey, static::API_VERSION), new EntityFactory());
    }
}
