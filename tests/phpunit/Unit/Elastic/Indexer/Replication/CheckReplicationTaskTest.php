<?php

namespace SMW\Tests\Elastic\Indexer\Replication;

use SMW\Elastic\Indexer\Replication\CheckReplicationTask;
use SMW\Tests\PHPUnitCompat;
use SMW\DIWikiPage;
use SMW\DIProperty;
use SMWDITime as DITime;

/**
 * @covers \SMW\Elastic\Indexer\Replication\CheckReplicationTask
 * @group semantic-mediawiki
 *
 * @license GNU GPL v2+
 * @since 3.0
 *
 * @author mwjames
 */
class CheckReplicationTaskTest extends \PHPUnit_Framework_TestCase {

	use PHPUnitCompat;

	private $store;
	private $replicationStatus;
	private $entityCache;
	private $elasticClient;
	private $idTable;

	protected function setUp() {

		$this->idTable = $this->getMockBuilder( '\SMWSql3SmwIds' )
			->disableOriginalConstructor()
			->getMock();

		$this->store = $this->getMockBuilder( '\SMW\Store' )
			->disableOriginalConstructor()
			->setMethods( [ 'getObjectIds', 'getConnection' ] )
			->getMockForAbstractClass();

		$this->store->expects( $this->any() )
			->method( 'getObjectIds' )
			->will( $this->returnValue( $this->idTable ) );

		$this->replicationStatus = $this->getMockBuilder( '\SMW\Elastic\Indexer\Replication\ReplicationStatus' )
			->disableOriginalConstructor()
			->getMock();

		$this->elasticClient = $this->getMockBuilder( '\SMW\Elastic\Connection\DummyClient' )
			->disableOriginalConstructor()
			->getMock();

		$this->elasticClient->expects( $this->any() )
			->method( 'ping' )
			->will( $this->returnValue( true ) );

		$this->entityCache = $this->getMockBuilder( '\SMW\EntityCache' )
			->disableOriginalConstructor()
			->getMock();
	}

	public function testCanConstruct() {

		$this->assertInstanceOf(
			CheckReplicationTask::class,
			new CheckReplicationTask( $this->store, $this->replicationStatus, $this->entityCache )
		);
	}

	public function testCheckReplication_NotExists() {

		$this->elasticClient->expects( $this->any() )
			->method( 'hasMaintenanceLock' )
			->will( $this->returnValue( false ) );

		$replicationStatus = [
			'modification_date' => false
		];

		$this->replicationStatus->expects( $this->once() )
			->method( 'get' )
			->with(	$this->equalTo( 'modification_date_associated_revision' ) )
			->will( $this->returnValue( $replicationStatus ) );

		$this->store->expects( $this->once() )
			->method( 'getPropertyValues' )
			->will( $this->returnValue( [] ) );

		$this->store->expects( $this->any() )
			->method( 'getConnection' )
			->will( $this->returnValue( $this->elasticClient ) );

		$instance = new CheckReplicationTask(
			$this->store,
			$this->replicationStatus,
			$this->entityCache
		);

		$html = $instance->checkReplication( DIWikiPage::newFromText( 'Foo' ), [] );

		$this->assertContains(
			'smw-highlighter',
			$html
		);
	}

	public function testCheckReplication_NoConnection() {

		$elasticClient = $this->getMockBuilder( '\SMW\Elastic\Connection\DummyClient' )
			->disableOriginalConstructor()
			->getMock();

		$elasticClient->expects( $this->any() )
			->method( 'ping' )
			->will( $this->returnValue( false ) );

		$elasticClient->expects( $this->never() )
			->method( 'hasMaintenanceLock' );

		$this->replicationStatus->expects( $this->never() )
			->method( 'get' );

		$this->store->expects( $this->any() )
			->method( 'getConnection' )
			->will( $this->returnValue( $elasticClient ) );

		$instance = new CheckReplicationTask(
			$this->store,
			$this->replicationStatus,
			$this->entityCache
		);

		$html = $instance->checkReplication( DIWikiPage::newFromText( 'Foo' ), [] );

		$this->assertContains(
			'smw-highlighter',
			$html
		);
	}

	public function testCheckReplication_ModificationDate() {

		$config = $this->getMockBuilder( '\SMW\Options' )
			->disableOriginalConstructor()
			->getMock();

		$config->expects( $this->any() )
			->method( 'dotGet' )
			->with(	$this->equalTo( 'indexer.experimental.file.ingest' ) )
			->will( $this->returnValue( true ) );

		$this->elasticClient->expects( $this->any() )
			->method( 'hasMaintenanceLock' )
			->will( $this->returnValue( false ) );

		$this->elasticClient->expects( $this->any() )
			->method( 'getConfig' )
			->will( $this->returnValue( $config ) );

		$subject = DIWikiPage::newFromText( 'Foo' );
		$time_es = DITime::newFromTimestamp( 1272508900 );
		$time_store = DITime::newFromTimestamp( 1272508903 );

		$replicationStatus = [
			'modification_date' => $time_es
		];

		$this->replicationStatus->expects( $this->once() )
			->method( 'get' )
			->with(	$this->equalTo( 'modification_date_associated_revision' ) )
			->will( $this->returnValue( $replicationStatus ) );

		$this->store->expects( $this->at( 4 ) )
			->method( 'getPropertyValues' )
			->will( $this->returnValue( [ $time_store ] ) );

		$this->store->expects( $this->any() )
			->method( 'getConnection' )
			->will( $this->returnValue( $this->elasticClient ) );

		$instance = new CheckReplicationTask(
			$this->store,
			$this->replicationStatus,
			$this->entityCache
		);

		$html = $instance->checkReplication( $subject, [] );

		$this->assertContains(
			'smw-highlighter',
			$html
		);

		$this->assertContains(
			'2010-04-29 02:41:43',
			$html
		);
	}

	public function testCheckReplication_AssociateRev() {

		$config = $this->getMockBuilder( '\SMW\Options' )
			->disableOriginalConstructor()
			->getMock();

		$config->expects( $this->any() )
			->method( 'dotGet' )
			->with(	$this->equalTo( 'indexer.experimental.file.ingest' ) )
			->will( $this->returnValue( true ) );

		$this->elasticClient->expects( $this->any() )
			->method( 'hasMaintenanceLock' )
			->will( $this->returnValue( false ) );

		$this->elasticClient->expects( $this->any() )
			->method( 'getConfig' )
			->will( $this->returnValue( $config ) );

		$subject = DIWikiPage::newFromText( 'Foo' );
		$time = DITime::newFromTimestamp( 1272508903 );

		$replicationStatus = [
			'modification_date' => $time,
			'associated_revision' => 99999
		];

		$this->replicationStatus->expects( $this->once() )
			->method( 'get' )
			->with(	$this->equalTo( 'modification_date_associated_revision' ) )
			->will( $this->returnValue( $replicationStatus ) );

		$this->idTable->expects( $this->at( 1 ) )
			->method( 'findAssociatedRev' )
			->will( $this->returnValue( 42 ) );

		$this->store->expects( $this->at( 4 ) )
			->method( 'getPropertyValues' )
			->will( $this->returnValue( [ $time ] ) );

		$this->store->expects( $this->any() )
			->method( 'getConnection' )
			->will( $this->returnValue( $this->elasticClient ) );

		$instance = new CheckReplicationTask(
			$this->store,
			$this->replicationStatus,
			$this->entityCache
		);

		$html = $instance->checkReplication( $subject, [] );

		$this->assertContains(
			'smw-highlighter',
			$html
		);

		$this->assertContains(
			'99999',
			$html
		);
	}

	public function testCheckReplication_File() {

		$config = $this->getMockBuilder( '\SMW\Options' )
			->disableOriginalConstructor()
			->getMock();

		$config->expects( $this->any() )
			->method( 'dotGet' )
			->with(	$this->equalTo( 'indexer.experimental.file.ingest' ) )
			->will( $this->returnValue( true ) );

		$this->elasticClient->expects( $this->any() )
			->method( 'hasMaintenanceLock' )
			->will( $this->returnValue( false ) );

		$this->elasticClient->expects( $this->any() )
			->method( 'getConfig' )
			->will( $this->returnValue( $config ) );

		$subject = DIWikiPage::newFromText( 'Foo', NS_FILE );
		$time = DITime::newFromTimestamp( 1272508903 );

		$replicationStatus = [
			'modification_date' => $time,
			'associated_revision' => 9999
		];

		$this->replicationStatus->expects( $this->once() )
			->method( 'get' )
			->with(	$this->equalTo( 'modification_date_associated_revision' ) )
			->will( $this->returnValue( $replicationStatus ) );

		$this->idTable->expects( $this->any() )
			->method( 'findAssociatedRev' )
			->will( $this->returnValue( 9999 ) );

		$this->store->expects( $this->at( 4 ) )
			->method( 'getPropertyValues' )
			->will( $this->returnValue( [ $time ] ) );

		$this->store->expects( $this->at( 6 ) )
			->method( 'getPropertyValues' )
			->with(
				$this->equalTo( $subject ),
				$this->equalTo( new DIProperty( '_FILE_ATTCH' ) ) )
			->will( $this->returnValue( [] ) );

		$this->store->expects( $this->any() )
			->method( 'getConnection' )
			->will( $this->returnValue( $this->elasticClient ) );

		$instance = new CheckReplicationTask(
			$this->store,
			$this->replicationStatus,
			$this->entityCache
		);

		$html = $instance->checkReplication( $subject, [] );

		$this->assertContains(
			'smw-highlighter',
			$html
		);
	}

	public function testMakeCacheKey() {

		$subject = DIWikiPage::newFromText( 'Foo', NS_MAIN );

		$this->assertSame(
			CheckReplicationTask::makeCacheKey( $subject->getHash() ),
			CheckReplicationTask::makeCacheKey( $subject )
		);
	}

	public function testGetReplicationFailures() {

		$this->entityCache->expects( $this->once() )
			->method( 'fetch' )
			->with( $this->stringContains( 'smw:entity:1ce32bc49b4f8bc82a53098238ded208' ) );

		$instance = new CheckReplicationTask(
			$this->store,
			$this->replicationStatus,
			$this->entityCache
		);

		$instance->getReplicationFailures();
	}

	public function testDeleteReplicationTrail_OnTitle() {

		$subject = DIWikiPage::newFromText( 'Foo', NS_MAIN );

		$this->entityCache->expects( $this->once() )
			->method( 'deleteSub' )
			->with(
				$this->stringContains( 'smw:entity:1ce32bc49b4f8bc82a53098238ded208' ),
				$this->stringContains( 'smw:entity:b94628b92d22cd315ccf7abb5b1df3c0' ) );

		$instance = new CheckReplicationTask(
			$this->store,
			$this->replicationStatus,
			$this->entityCache
		);

		$instance->deleteReplicationTrail( $subject->getTitle() );
	}

	public function testDeleteReplicationTrail_OnSubject() {

		$subject = DIWikiPage::newFromText( 'Foo', NS_MAIN );

		$this->entityCache->expects( $this->once() )
			->method( 'deleteSub' )
			->with(
				$this->stringContains( 'smw:entity:1ce32bc49b4f8bc82a53098238ded208' ),
				$this->stringContains( 'smw:entity:b94628b92d22cd315ccf7abb5b1df3c0' ) );

		$instance = new CheckReplicationTask(
			$this->store,
			$this->replicationStatus,
			$this->entityCache
		);

		$instance->deleteReplicationTrail( $subject );
	}

}
