<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Support\OfficialAnalysisCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ReceivingCustomerController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim($request->string('q')->toString());

        $customers = Customer::query()
            ->when($search !== '', function ($query) use ($search) {
                $like = '%'.$search.'%';
                $query->where(function ($inner) use ($like) {
                    $inner->where('customer_name', 'like', $like)
                        ->orWhere('company_name', 'like', $like)
                        ->orWhere('customer_email', 'like', $like)
                        ->orWhere('customer_contact', 'like', $like);
                });
            })
            ->orderBy('customer_name')
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Customer $customer) => [
                'id' => $customer->id,
                'customer_name' => $customer->customer_name,
                'customer_email' => $customer->customer_email,
                'customer_contact' => $customer->customer_contact,
                'customer_address' => $customer->customer_address,
                'company_name' => $customer->company_name,
                'ownership_type' => $customer->ownership_type,
                'updated_at' => $customer->updated_at
                    ?->timezone((string) config('app.lab_timezone', 'Asia/Manila'))
                    ->format('M j, Y'),
            ]);

        return Inertia::render('customers/index', [
            'customers' => $customers,
            'filters' => [
                'q' => $search,
            ],
            'ownership_types' => OfficialAnalysisCatalog::ownershipTypes(),
            'total' => Customer::query()->count(),
        ]);
    }

    public function update(Request $request, Customer $customer): RedirectResponse
    {
        $optional = [
            'customer_email' => ['nullable', 'email', 'max:255'],
            'customer_contact' => ['nullable', 'string', 'max:255'],
            'customer_address' => ['nullable', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'ownership_type' => ['nullable', 'string', Rule::in(OfficialAnalysisCatalog::ownershipTypes())],
        ];
        $rules = [
            'customer_name' => ['required', 'string', 'max:255'],
        ];
        $merged = [];
        foreach ($optional as $key => $rule) {
            if (! $request->exists($key)) {
                continue;
            }
            $merged[$key] = filled($request->input($key)) ? $request->input($key) : null;
            $rules[$key] = $rule;
        }
        $request->merge($merged);

        $data = $request->validate($rules);
        $updates = [
            'customer_name' => trim($data['customer_name']),
        ];
        foreach (array_keys($optional) as $key) {
            if (! array_key_exists($key, $data)) {
                continue;
            }
            $value = $data[$key];
            $updates[$key] = is_string($value) ? trim($value) : $value;
            if ($updates[$key] === '') {
                $updates[$key] = null;
            }
        }

        $customer->update($updates);

        return back();
    }
}
