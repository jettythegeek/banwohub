<?php

namespace App\Http\Controllers\Api\V1\Portal;

use App\Http\Controllers\Controller;
use App\Http\Concerns\ResolvesPortalClient;
use App\Http\Resources\PortalInvoiceResource;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PortalInvoiceController extends Controller
{
    use ResolvesPortalClient;

    public function index(Request $request): AnonymousResourceCollection
    {
        $client = $this->portalClientFor($request->user());

        $invoices = Invoice::query()
            ->with(['legalMatter:id,title,matter_number', 'lineItems'])
            ->where('client_id', $client->id)
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->latest('issue_date')
            ->paginate($request->integer('per_page', 15));

        return PortalInvoiceResource::collection($invoices);
    }

    public function show(Request $request, Invoice $invoice): PortalInvoiceResource
    {
        $client = $this->portalClientFor($request->user());

        abort_unless(
            $invoice->client_id === $client->id
            && ! in_array($invoice->status, ['draft', 'cancelled'], true),
            404,
        );

        $invoice->load(['legalMatter:id,title,matter_number', 'lineItems']);

        return new PortalInvoiceResource($invoice);
    }
}
