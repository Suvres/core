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

namespace ApiPlatform\Tests\Doctrine\Orm\State;

use ApiPlatform\Core\Tests\ProphecyTrait;
use ApiPlatform\Doctrine\Orm\State\Processor;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\Tests\Fixtures\TestBundle\Entity\Dummy;
use Doctrine\ODM\MongoDB\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\TestCase;
use Prophecy\Prediction\CallPrediction;
use Prophecy\Prediction\NoCallsPrediction;

class ProcessorTest extends TestCase
{
    use ProphecyTrait;

    public function testConstruct()
    {
        $this->assertInstanceOf(ProcessorInterface::class, new Processor($this->prophesize(ManagerRegistry::class)->reveal()));
    }

    public function testSupports()
    {
        $managerRegistryProphecy = $this->prophesize(ManagerRegistry::class);
        $managerRegistryProphecy->getManagerForClass(Dummy::class)->willReturn($this->prophesize(ObjectManager::class)->reveal())->shouldBeCalled();

        $this->assertTrue((new Processor($managerRegistryProphecy->reveal()))->supports(new Dummy()));
    }

    public function testDoesNotSupport()
    {
        $this->assertFalse((new Processor($this->prophesize(ManagerRegistry::class)->reveal()))->supports('dummy'));
    }

    public function testPersist()
    {
        $dummy = new Dummy();

        $objectManagerProphecy = $this->prophesize(ObjectManager::class);
        $objectManagerProphecy->contains($dummy)->willReturn(false);
        $objectManagerProphecy->persist($dummy)->shouldBeCalled();
        $objectManagerProphecy->flush()->shouldBeCalled();
        $objectManagerProphecy->refresh($dummy)->shouldBeCalled();

        $managerRegistryProphecy = $this->prophesize(ManagerRegistry::class);
        $managerRegistryProphecy->getManagerForClass(Dummy::class)->willReturn($objectManagerProphecy->reveal())->shouldBeCalled();

        $result = (new Processor($managerRegistryProphecy->reveal()))->process($dummy);
        $this->assertSame($dummy, $result);
    }

    public function testPersistIfEntityAlreadyManaged()
    {
        $dummy = new Dummy();

        $objectManagerProphecy = $this->prophesize(ObjectManager::class);
        $objectManagerProphecy->contains($dummy)->willReturn(true);
        $objectManagerProphecy->persist($dummy)->shouldNotBeCalled();
        $objectManagerProphecy->flush()->shouldBeCalled();
        $objectManagerProphecy->refresh($dummy)->shouldBeCalled();
        $objectManagerProphecy->getClassMetadata(Dummy::class)->willReturn(null)->shouldBeCalled();

        $managerRegistryProphecy = $this->prophesize(ManagerRegistry::class);
        $managerRegistryProphecy->getManagerForClass(Dummy::class)->willReturn($objectManagerProphecy->reveal())->shouldBeCalled();

        $result = (new Processor($managerRegistryProphecy->reveal()))->process($dummy);
        $this->assertSame($dummy, $result);
    }

    public function testPersistWithNullManager()
    {
        $dummy = new Dummy();

        $managerRegistryProphecy = $this->prophesize(ManagerRegistry::class);
        $managerRegistryProphecy->getManagerForClass(Dummy::class)->willReturn(null)->shouldBeCalled();

        $result = (new Processor($managerRegistryProphecy->reveal()))->process($dummy);
        $this->assertSame($dummy, $result);
    }

    public function testRemove()
    {
        $dummy = new Dummy();

        $objectManagerProphecy = $this->prophesize(ObjectManager::class);
        $objectManagerProphecy->remove($dummy)->shouldBeCalled();
        $objectManagerProphecy->flush()->shouldBeCalled();

        $managerRegistryProphecy = $this->prophesize(ManagerRegistry::class);
        $managerRegistryProphecy->getManagerForClass(Dummy::class)->willReturn($objectManagerProphecy->reveal())->shouldBeCalled();

        (new Processor($managerRegistryProphecy->reveal()))->process($dummy, [], null, ['operation' => new Delete()]);
    }

    public function testRemoveWithNullManager()
    {
        $managerRegistryProphecy = $this->prophesize(ManagerRegistry::class);
        $managerRegistryProphecy->getManagerForClass(Dummy::class)->willReturn(null)->shouldBeCalled();

        (new Processor($managerRegistryProphecy->reveal()))->process(new Dummy(), [], null, ['operation' => new Delete()]);
    }

    public function getTrackingPolicyParameters()
    {
        return [
            'deferred explicit ORM' => [ClassMetadataInfo::class, true, true],
            'deferred implicit ORM' => [ClassMetadataInfo::class, false, false],
            'deferred explicit ODM' => [ClassMetadata::class, true, true],
            'deferred implicit ODM' => [ClassMetadata::class, false, false],
        ];
    }

    /**
     * @dataProvider getTrackingPolicyParameters
     *
     * @param mixed $metadataClass
     * @param mixed $deferredExplicit
     * @param mixed $persisted
     */
    public function testTrackingPolicy($metadataClass, $deferredExplicit, $persisted)
    {
        $dummy = new Dummy();

        $classMetadataInfo = $this->prophesize($metadataClass);
        if (method_exists($metadataClass, 'isChangeTrackingDeferredExplicit')) {
            $classMetadataInfo->isChangeTrackingDeferredExplicit()->willReturn($deferredExplicit)->shouldBeCalled();
        } else {
            $persisted = false;
        }

        $objectManagerProphecy = $this->prophesize(ObjectManager::class);
        $objectManagerProphecy->getClassMetadata(Dummy::class)->willReturn($classMetadataInfo)->shouldBeCalled();
        $objectManagerProphecy->contains($dummy)->willReturn(true);
        $objectManagerProphecy->persist($dummy)->should($persisted ? new CallPrediction() : new NoCallsPrediction());
        $objectManagerProphecy->flush()->shouldBeCalled();
        $objectManagerProphecy->refresh($dummy)->shouldBeCalled();

        $managerRegistryProphecy = $this->prophesize(ManagerRegistry::class);
        $managerRegistryProphecy->getManagerForClass(Dummy::class)->willReturn($objectManagerProphecy)->shouldBeCalled();

        $result = (new Processor($managerRegistryProphecy->reveal()))->process($dummy);
        $this->assertSame($dummy, $result);
    }
}
