<?php

namespace Webkul\Admin\Http\Controllers\Configuration;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Admin\Http\Requests\ConfigurationForm;
use Webkul\Core\Repositories\CoreConfigRepository as ConfigurationRepository;
use Webkul\Core\Services\CrmAi;

class ConfigurationController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(protected ConfigurationRepository $configurationRepository) {}

    /**
     * Display a listing of the resource.
     */
    public function index(): View|RedirectResponse
    {
        if (request()->route('slug') === 'general' && request()->route('slug2') === 'magic_ai') {
            return redirect()->route('admin.configuration.index', ['slug' => 'general', 'slug2' => 'crm_ai']);
        }

        if (
            request()->route('slug')
            && request()->route('slug2')
        ) {
            return view('admin::configuration.edit');
        }

        return view('admin::configuration.index');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(ConfigurationForm $request): RedirectResponse
    {
        Event::dispatch('core.configuration.save.before');

        $data = $request->all();
        unset($data['general']['magic_ai']);

        foreach (CrmAi::keyFields() as $field) {
            if (blank(data_get($data, $field))) {
                Arr::forget($data, $field);
            }
        }

        $this->configurationRepository->create($data);

        Event::dispatch('core.configuration.save.after');

        session()->flash('success', trans('admin::app.configuration.index.save-success'));

        return redirect()->back();
    }

    /**
     * download the file for the specified resource.
     *
     * @return Response
     */
    public function download()
    {
        $path = request()->route()->parameters()['path'];

        $fileName = 'configuration/'.$path;

        $config = $this->configurationRepository->findOneByField('value', $fileName);

        return Storage::download($config['value']);
    }

    /**
     * Search for configurations.
     */
    public function search(): JsonResponse
    {
        $results = $this->configurationRepository->search(
            system_config()->getItems(),
            request()->query('query')
        );

        return new JsonResponse([
            'data' => $results,
        ]);
    }
}
