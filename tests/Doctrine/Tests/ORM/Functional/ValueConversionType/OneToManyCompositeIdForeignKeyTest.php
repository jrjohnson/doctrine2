<?php

namespace Doctrine\Tests\ORM\Functional\ValueConversionType;

use Doctrine\Tests\Models\ValueConversionType as Entity;
use Doctrine\Tests\OrmFunctionalTestCase;

/**
 * The entities all use a custom type that converst the value as identifier(s).
 * {@see \Doctrine\Tests\DbalTypes\Rot13Type}
 *
 * Test that OneToMany associations with composite id of which one is a
 * association itself work correctly.
 *
 * @group DDC-3380
 */
class OneToManyCompositeIdForeignKeyTest extends OrmFunctionalTestCase
{
    public function setUp()
    {
        $this->useModelSet('vct_onetomany_compositeid_foreignkey');

        parent::setUp();

        $auxiliary = new Entity\AuxiliaryEntity();
        $auxiliary->id4 = 'abc';

        $inversed = new Entity\InversedOneToManyCompositeIdForeignKeyEntity();
        $inversed->id1 = 'def';
        $inversed->foreignEntity = $auxiliary;
        $inversed->someProperty = 'some value to be loaded';

        $owning = new Entity\OwningManyToOneCompositeIdForeignKeyEntity();
        $owning->id2 = 'ghi';

        $inversed->associatedEntities->add($owning);
        $owning->associatedEntity = $inversed;

        $this->_em->persist($auxiliary);
        $this->_em->persist($inversed);
        $this->_em->persist($owning);

        $this->_em->flush();
        $this->_em->clear();
    }

    public static function tearDownAfterClass()
    {
        $conn = static::$_sharedConn;

        $conn->executeUpdate('DROP TABLE vct_owning_manytoone_compositeid_foreignkey');
        $conn->executeUpdate('DROP TABLE vct_inversed_onetomany_compositeid_foreignkey');
        $conn->executeUpdate('DROP TABLE vct_auxiliary');
    }

    public function testThatTheValueOfIdentifiersAreConvertedInTheDatabase()
    {
        $conn = $this->_em->getConnection();

        self::assertEquals('nop', $conn->fetchColumn('SELECT id4 FROM vct_auxiliary LIMIT 1'));

        self::assertEquals('qrs', $conn->fetchColumn('SELECT id1 FROM vct_inversed_onetomany_compositeid_foreignkey LIMIT 1'));
        self::assertEquals('nop', $conn->fetchColumn('SELECT foreign_id FROM vct_inversed_onetomany_compositeid_foreignkey LIMIT 1'));

        self::assertEquals('tuv', $conn->fetchColumn('SELECT id2 FROM vct_owning_manytoone_compositeid_foreignkey LIMIT 1'));
        self::assertEquals('qrs', $conn->fetchColumn('SELECT associated_id FROM vct_owning_manytoone_compositeid_foreignkey LIMIT 1'));
        self::assertEquals('nop', $conn->fetchColumn('SELECT associated_foreign_id FROM vct_owning_manytoone_compositeid_foreignkey LIMIT 1'));
    }

    /**
     * @depends testThatTheValueOfIdentifiersAreConvertedInTheDatabase
     */
    public function testThatEntitiesAreFetchedFromTheDatabase()
    {
        $auxiliary = $this->_em->find(
            'Doctrine\Tests\Models\ValueConversionType\AuxiliaryEntity',
            'abc'
        );

        $inversed = $this->_em->find(
            'Doctrine\Tests\Models\ValueConversionType\InversedOneToManyCompositeIdForeignKeyEntity',
            array('id1' => 'def', 'foreignEntity' => 'abc')
        );

        $owning = $this->_em->find(
            'Doctrine\Tests\Models\ValueConversionType\OwningManyToOneCompositeIdForeignKeyEntity',
            'ghi'
        );

        self::assertInstanceOf('Doctrine\Tests\Models\ValueConversionType\AuxiliaryEntity', $auxiliary);
        self::assertInstanceOf('Doctrine\Tests\Models\ValueConversionType\InversedOneToManyCompositeIdForeignKeyEntity', $inversed);
        self::assertInstanceOf('Doctrine\Tests\Models\ValueConversionType\OwningManyToOneCompositeIdForeignKeyEntity', $owning);
    }

    /**
     * @depends testThatEntitiesAreFetchedFromTheDatabase
     */
    public function testThatTheValueOfIdentifiersAreConvertedBackAfterBeingFetchedFromTheDatabase()
    {
        $auxiliary = $this->_em->find(
            'Doctrine\Tests\Models\ValueConversionType\AuxiliaryEntity',
            'abc'
        );

        $inversed = $this->_em->find(
            'Doctrine\Tests\Models\ValueConversionType\InversedOneToManyCompositeIdForeignKeyEntity',
            array('id1' => 'def', 'foreignEntity' => 'abc')
        );

        $owning = $this->_em->find(
            'Doctrine\Tests\Models\ValueConversionType\OwningManyToOneCompositeIdForeignKeyEntity',
            'ghi'
        );

        self::assertEquals('abc', $auxiliary->id4);
        self::assertEquals('def', $inversed->id1);
        self::assertEquals('abc', $inversed->foreignEntity->id4);
        self::assertEquals('ghi', $owning->id2);
    }

    /**
     * @depends testThatTheValueOfIdentifiersAreConvertedBackAfterBeingFetchedFromTheDatabase
     */
    public function testThatInversedEntityIsFetchedFromTheDatabaseUsingAuxiliaryEntityAsId()
    {
        $auxiliary = $this->_em->find(
            'Doctrine\Tests\Models\ValueConversionType\AuxiliaryEntity',
            'abc'
        );

        $inversed = $this->_em->find(
            'Doctrine\Tests\Models\ValueConversionType\InversedOneToManyCompositeIdForeignKeyEntity',
            array('id1' => 'def', 'foreignEntity' => $auxiliary)
        );

        self::assertInstanceOf('Doctrine\Tests\Models\ValueConversionType\InversedOneToManyCompositeIdForeignKeyEntity', $inversed);
    }

    /**
     * @depends testThatEntitiesAreFetchedFromTheDatabase
     */
    public function testThatTheProxyFromOwningToInversedIsLoaded()
    {
        $owning = $this->_em->find(
            'Doctrine\Tests\Models\ValueConversionType\OwningManyToOneCompositeIdForeignKeyEntity',
            'ghi'
        );

        $inversedProxy = $owning->associatedEntity;

        self::assertSame('def', $inversedProxy->id1, 'Proxy identifier is converted');

        self::assertEquals('some value to be loaded', $inversedProxy->someProperty);
    }

    /**
     * @depends testThatEntitiesAreFetchedFromTheDatabase
     */
    public function testThatTheCollectionFromInversedToOwningIsLoaded()
    {
        $inversed = $this->_em->find(
            'Doctrine\Tests\Models\ValueConversionType\InversedOneToManyCompositeIdForeignKeyEntity',
            array('id1' => 'def', 'foreignEntity' => 'abc')
        );

        self::assertCount(1, $inversed->associatedEntities);
    }
}
