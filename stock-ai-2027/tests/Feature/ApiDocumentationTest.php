<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class ApiDocumentationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_scalar_loads_the_application_openapi_document(): void
    {
        Gate::define('viewApiDocs', fn (?User $user): bool => true);

        $this->get('/scalar')->assertOk()->assertSee(json_encode('/docs/api.json'), false)
            ->assertSee('Scalar.createApiReference', false);
    }

    public function test_openapi_includes_all_company_endpoints_and_query_filters(): void
    {
        Gate::define('viewApiDocs', fn (?User $user): bool => true);

        $response = $this->getJson('/docs/api.json')->assertOk()
            ->assertJsonStructure(['openapi', 'paths', 'components']);

        $document = $response->json();
        $this->assertArrayHasKey('get', $document['paths']['/v1/companies']);
        $this->assertArrayHasKey('post', $document['paths']['/v1/companies/sync']);
        $this->assertArrayHasKey('get', $document['paths']['/v1/companies/{ticker}']);
        $this->assertSame(['search', 'com_group_code', 'icb_code', 'per_page', 'page'],
            array_column($document['paths']['/v1/companies']['get']['parameters'], 'name'));
        $this->assertArrayHasKey('CompanyResource', $document['components']['schemas']);
        $this->assertArrayHasKey('get', $document['paths']['/v1/icbs']);
        $this->assertArrayHasKey('get', $document['paths']['/v1/icbs/{code}']);
        $this->assertArrayHasKey('IcbResource', $document['components']['schemas']);
    }

    public function test_docs_are_restricted_outside_local_without_the_docs_gate(): void
    {
        $this->get('/scalar')->assertForbidden();
        $this->getJson('/docs/api.json')->assertForbidden();
    }
}
