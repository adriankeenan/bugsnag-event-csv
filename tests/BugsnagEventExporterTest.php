<?php

use Keenan\BugsnagClient;
use Keenan\BugsnagEventExporter;
use PHPUnit\Framework\TestCase;

class BugsnagEventExporterTest extends TestCase
{
    protected BugsnagEventExporter $eventExporter;

    private function getEventExporter(): BugsnagEventExporter
    {
        $bugsnagClientStub = $this->createStub(BugsnagClient::class);

        $bugsnagClientStub->method('getOrganisations')
             ->willReturn([
                 [
                     'id' => 'org_id',
                     'slug' => 'org_slug',
                 ]
             ]);

        $bugsnagClientStub->method('getProjects')
            ->willReturn([
                [
                    'id' => 'project_id',
                    'slug' => 'project_slug',
                ]
            ]);

        $bugsnagClientStub->method('getEvents')
            ->willReturn([
                [
                    'id' => 'foo',
                    'received_at' => 'bar',
                    'metaData' => [
                        'true' => true,
                        'false' => false,
                        'null' => null,
                    ],
                ]
            ]);

        return new BugsnagEventExporter($bugsnagClientStub);
    }

    public function setUp(): void
    {
        parent::setUp();

        $this->eventExporter = $this->getEventExporter();
    }

    public function testExportCsv()
    {
        $csv = $this->eventExporter
            ->setOrganisation('org_id')
            ->setProject('project_id')
            ->setErrorId('error_id')
            ->exportCsv(
                1,
                [
                    'metaData.true',
                    'metaData.false',
                    'metaData.null',
                    'metaData.not_set',
                    'metaData.true:true',
                ],
                '__not_set',
                '__true',
                '__false',
                '__null',
            );


        $expectedCsv = <<<EOT
id,received_at,metaData.true,metaData.false,metaData.null,metaData.not_set,true
foo,bar,__true,__false,__null,__not_set,__true

EOT;

        $this->assertEquals($csv, $expectedCsv);
    }
}