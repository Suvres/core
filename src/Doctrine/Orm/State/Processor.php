<?php

/*
 * This file is part of the API Platform project.
 *
 * (c) Kévin Dunglas <dunglas@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ApiPlatform\Doctrine\Orm\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\Util\ClassInfoTrait;
use Doctrine\ODM\MongoDB\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager as DoctrineObjectManager;

final class Processor implements ProcessorInterface
{
    use ClassInfoTrait;

    private $managerRegistry;

    public function __construct(ManagerRegistry $managerRegistry)
    {
        $this->managerRegistry = $managerRegistry;
    }

    public function resumable(?string $operationName = null, array $context = []): bool
    {
        return false;
    }

    public function supports($data, array $identifiers = [], ?string $operationName = null, array $context = []): bool
    {
        return null !== $this->getManager($data);
    }

    private function persist($data, array $context = [])
    {
        if (!$manager = $this->getManager($data)) {
            return $data;
        }

        if (!$manager->contains($data) || $this->isDeferredExplicit($manager, $data)) {
            $manager->persist($data);
        }

        $manager->flush();
        $manager->refresh($data);

        return $data;
    }

    private function remove($data, array $context = [])
    {
        if (!$manager = $this->getManager($data)) {
            return;
        }

        $manager->remove($data);
        $manager->flush();
    }

    public function process($data, array $identifiers = [], ?string $operationName = null, array $context = [])
    {
        if (\array_key_exists('operation', $context) && Operation::METHOD_DELETE === ($context['operation']->getMethod() ?? null)) {
            return $this->remove($data);
        }

        return $this->persist($data);
    }

    /**
     * Gets the Doctrine object manager associated with given data.
     *
     * @param mixed $data
     */
    private function getManager($data): ?DoctrineObjectManager
    {
        return \is_object($data) ? $this->managerRegistry->getManagerForClass($this->getObjectClass($data)) : null;
    }

    /**
     * Checks if doctrine does not manage data automatically.
     *
     * @param mixed $data
     */
    private function isDeferredExplicit(DoctrineObjectManager $manager, $data): bool
    {
        $classMetadata = $manager->getClassMetadata($this->getObjectClass($data));
        if (($classMetadata instanceof ClassMetadataInfo || $classMetadata instanceof ClassMetadata) && method_exists($classMetadata, 'isChangeTrackingDeferredExplicit')) {
            return $classMetadata->isChangeTrackingDeferredExplicit();
        }

        return false;
    }
}
