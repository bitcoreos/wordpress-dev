<?php
/**
 * Repository Pattern Implementation
 *
 * Use for data access abstraction
 * Pattern extracted from userswp and mcp-adapter repositories
 *
 * @package BITCORE\Patterns
 * @since 1.0.0
 * @version     1.0.0
 */

declare(strict_types=1);

namespace BITCORE\Patterns;

/**
 * Repository Interface
 *
 * Defines contract for repository implementations.
 *
 * @since 1.0.0
 * @template T
 */
interface RepositoryInterface {
    /**
     * Find entity by ID.
     *
     * @param int|string $id Entity ID.
     * @return T|null Entity or null.
     */
    public function find($id);

    /**
     * Find all entities.
     *
     * @return array<T> Array of entities.
     */
    public function findAll(): array;

    /**
     * Find by criteria.
     *
     * @param array<string,mixed> $criteria Search criteria.
     * @param array<string,mixed> $orderBy Sort order.
     * @param int|null $limit Result limit.
     * @param int|null $offset Result offset.
     * @return array<T> Array of entities.
     */
    public function findBy(array $criteria, array $orderBy = [], ?int $limit = null, ?int $offset = null): array;

    /**
     * Find one by criteria.
     *
     * @param array<string,mixed> $criteria Search criteria.
     * @return T|null Entity or null.
     */
    public function findOneBy(array $criteria);

    /**
     * Save entity.
     *
     * @param T $entity Entity to save.
     * @return bool True on success.
     */
    public function save($entity): bool;

    /**
     * Delete entity.
     *
     * @param int|string|T $idOrEntity ID or entity to delete.
     * @return bool True on success.
     */
    public function delete($idOrEntity): bool;
}

/**
 * Abstract Repository Base
 *
 * Provides common repository functionality.
 *
 * @since 1.0.0
 * @template T
 */
abstract class AbstractRepository implements RepositoryInterface {
    /**
     * Database table name.
     *
     * @var string
     */
    protected string $table;

    /**
     * Entity class name.
     *
     * @var class-string<T>
     */
    protected string $entityClass;

    /**
     * WordPress database instance.
     *
     * @var \wpdb
     */
    protected $wpdb;

    /**
     * Constructor.
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
    }

    /**
     * Map database row to entity.
     *
     * @param \stdClass $row Database row.
     * @return T Entity instance.
     */
    abstract protected function mapRowToEntity(\stdClass $row);

    /**
     * {@inheritDoc}
     */
    public function find($id) {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->wpdb->prefix}{$this->table} WHERE id = %d",
                $id
            )
        );

        if (!$row) {
            return null;
        }

        return $this->mapRowToEntity($row);
    }

    /**
     * {@inheritDoc}
     */
    public function findAll(): array {
        $results = $this->wpdb->get_results(
            "SELECT * FROM {$this->wpdb->prefix}{$this->table} ORDER BY id ASC"
        );

        return array_map([$this, 'mapRowToEntity'], $results);
    }

    /**
     * {@inheritDoc}
     */
    public function findBy(array $criteria, array $orderBy = [], ?int $limit = null, ?int $offset = null): array {
        $where = [];
        $values = [];

        foreach ($criteria as $field => $value) {
            $where[] = "$field = %s";
            $values[] = $value;
        }

        $sql = "SELECT * FROM {$this->wpdb->prefix}{$this->table}";

        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }

        if (!empty($orderBy)) {
            $orderClauses = [];
            foreach ($orderBy as $field => $direction) {
                $orderClauses[] = "$field " . strtoupper($direction);
            }
            $sql .= " ORDER BY " . implode(', ', $orderClauses);
        }

        if ($limit !== null) {
            $sql .= " LIMIT %d";
            $values[] = $limit;
        }

        if ($offset !== null) {
            $sql .= " OFFSET %d";
            $values[] = $offset;
        }

        $results = $this->wpdb->get_results(
            $this->wpdb->prepare($sql, ...$values)
        );

        return array_map([$this, 'mapRowToEntity'], $results);
    }

    /**
     * {@inheritDoc}
     */
    public function findOneBy(array $criteria) {
        $results = $this->findBy($criteria, [], 1);
        return $results[0] ?? null;
    }
}

/**
 * Task Repository
 *
 * Repository for task entities.
 *
 * @since 1.0.0
 */
class TaskRepository extends AbstractRepository {
    /**
     * {@inheritDoc}
     */
    protected string $table = 'bitcore_tasks';

    /**
     * {@inheritDoc}
     */
    protected string $entityClass = TaskEntity::class;

    /**
     * {@inheritDoc}
     */
    protected function mapRowToEntity(\stdClass $row): TaskEntity {
        return new TaskEntity(
            (int) $row->id,
            $row->name,
            $row->description,
            $row->status,
            (int) $row->user_id,
            $row->created_at,
            $row->updated_at
        );
    }

    /**
     * {@inheritDoc}
     */
    public function save($entity): bool {
        if (!$entity instanceof TaskEntity) {
            return false;
        }

        $data = [
            'name' => $entity->getName(),
            'description' => $entity->getDescription(),
            'status' => $entity->getStatus(),
            'user_id' => $entity->getUserId(),
            'updated_at' => current_time('mysql'),
        ];

        if ($entity->getId()) {
            $result = $this->wpdb->update(
                $this->wpdb->prefix . $this->table,
                $data,
                ['id' => $entity->getId()],
                ['%s', '%s', '%s', '%d', '%s'],
                ['%d']
            );
        } else {
            $data['created_at'] = current_time('mysql');
            $result = $this->wpdb->insert(
                $this->wpdb->prefix . $this->table,
                $data,
                ['%s', '%s', '%s', '%d', '%s', '%s']
            );
        }

        return $result !== false;
    }

    /**
     * {@inheritDoc}
     */
    public function delete($idOrEntity): bool {
        $id = $idOrEntity instanceof TaskEntity ? $idOrEntity->getId() : $idOrEntity;

        $result = $this->wpdb->delete(
            $this->wpdb->prefix . $this->table,
            ['id' => $id],
            ['%d']
        );

        return $result !== false;
    }
}

/**
 * Task Entity
 *
 * Task domain entity.
 *
 * @since 1.0.0
 */
class TaskEntity {
    /**
     * Task ID.
     *
     * @var int|null
     */
    private ?int $id;

    /**
     * Task name.
     *
     * @var string
     */
    private string $name;

    /**
     * Task description.
     *
     * @var string
     */
    private string $description;

    /**
     * Task status.
     *
     * @var string
     */
    private string $status;

    /**
     * User ID.
     *
     * @var int
     */
    private int $userId;

    /**
     * Created at.
     *
     * @var string
     */
    private string $createdAt;

    /**
     * Updated at.
     *
     * @var string|null
     */
    private ?string $updatedAt;

    /**
     * Constructor.
     *
     * @param int|null $id Task ID.
     * @param string $name Task name.
     * @param string $description Task description.
     * @param string $status Task status.
     * @param int $userId User ID.
     * @param string $createdAt Created at.
     * @param string|null $updatedAt Updated at.
     */
    public function __construct(
        ?int $id,
        string $name,
        string $description,
        string $status,
        int $userId,
        string $createdAt,
        ?string $updatedAt = null
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->description = $description;
        $this->status = $status;
        $this->userId = $userId;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    public function getId(): ?int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function getDescription(): string { return $this->description; }
    public function getStatus(): string { return $this->status; }
    public function getUserId(): int { return $this->userId; }
    public function getCreatedAt(): string { return $this->createdAt; }
    public function getUpdatedAt(): ?string { return $this->updatedAt; }
}

/**
 * User Repository
 *
     * Repository for user entities.
     *
     * @since 1.0.0
     */
class UserRepository extends AbstractRepository {
    /**
     * {@inheritDoc}
     */
    protected string $table = 'users';

    /**
     * {@inheritDoc}
     */
    protected string $entityClass = UserEntity::class;

    /**
     * {@inheritDoc}
     */
    protected function mapRowToEntity(\stdClass $row): UserEntity {
        return new UserEntity(
            (int) $row->ID,
            $row->user_login,
            $row->user_email,
            $row->display_name,
            $row->user_registered
        );
    }

    /**
     * {@inheritDoc}
     */
    public function save($entity): bool {
        // WordPress users use wp_insert_user/wp_update_user
        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function delete($idOrEntity): bool {
        $id = $idOrEntity instanceof UserEntity ? $idOrEntity->getId() : $idOrEntity;
        return wp_delete_user($id) !== false;
    }
}

/**
 * User Entity
 *
 * User domain entity.
 *
 * @since 1.0.0
 */
class UserEntity {
    /**
     * User ID.
     *
     * @var int
     */
    private int $id;

    /**
     * User login.
     *
     * @var string
     */
    private string $login;

    /**
     * User email.
     *
     * @var string
     */
    private string $email;

    /**
     * Display name.
     *
     * @var string
     */
    private string $displayName;

    /**
     * Registered date.
     *
     * @var string
     */
    private string $registered;

    /**
     * Constructor.
     *
     * @param int $id User ID.
     * @param string $login User login.
     * @param string $email User email.
     * @param string $displayName Display name.
     * @param string $registered Registered date.
     */
    public function __construct(int $id, string $login, string $email, string $displayName, string $registered) {
        $this->id = $id;
        $this->login = $login;
        $this->email = $email;
        $this->displayName = $displayName;
        $this->registered = $registered;
    }

    public function getId(): int { return $this->id; }
    public function getLogin(): string { return $this->login; }
    public function getEmail(): string { return $this->email; }
    public function getDisplayName(): string { return $this->displayName; }
    public function getRegistered(): string { return $this->registered; }
}
