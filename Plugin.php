<?php

namespace AldirBlancValidadorRecurso;

use MapasCulturais\App;
use MapasCulturais\Entities\Registration;

class Plugin extends \AldirBlanc\PluginValidador
{
    function __construct(array $config = [])
    {
        $config += [
            'consolidacao_requer_homologacao' => false,
            'consolidacao_requer_validacoes' => false,

            // se true, só exporta as inscrições pendentes que já tenham alguma avaliação
            'exportador_requer_homologacao' => false,

            // se true, só exporta as inscrições 
            'exportador_requer_validacao' => [],
        ];
        $this->_config = $config;
        parent::__construct($config);
    }

    function _init()
    {
        $app = App::i();

        $plugin_aldirblanc = $app->plugins['AldirBlanc'];
        $plugin_validador = $this;

        $inciso1Ids = [$plugin_aldirblanc->config['inciso1_opportunity_id']];
        $inciso2Ids = array_values($plugin_aldirblanc->config['inciso2_opportunity_ids']);
        $inciso3Ids = is_array($plugin_aldirblanc->config['inciso3_opportunity_ids']) ? $plugin_aldirblanc->config['inciso3_opportunity_ids'] : [];
        
        $opportunities_ids = array_merge($inciso1Ids, $inciso2Ids, $inciso3Ids);

        // uploads de CSVs 
        $app->hook('template(opportunity.<<single|edit>>.sidebar-right):end', function () use($opportunities_ids, $plugin_aldirblanc, $plugin_validador) {
            $opportunity = $this->controller->requestedEntity; 
            if ($opportunity->canUser('@control') && in_array($opportunity->id, $opportunities_ids)) {
                if($opportunity->canUser('@control')){
                    $this->part('validador-recursos/validador-uploads', ['entity' => $opportunity, 'plugin_aldirblanc' => $plugin_aldirblanc, 'plugin_validador' => $plugin_validador]);
                }
            }
        });

                /**
         * @TODO: implementar para metodo de avaliação documental
         */
        $app->hook('entity(Registration).consolidateResult', function(&$result, $caller) use($plugin_validador, $app) {
            if ($recurso = $app->repo('RegistrationEvaluation')->findOneBy(['registration' => $caller->registration, 'user' => $plugin_validador->getUser()])) {
                $result = $caller->result;
            }
        }, 100000);

        parent::_init();
    }

    function register()
    {
        $app = App::i();
        $slug = $this->getSlug();

        $this->registerOpportunityMetadata($slug . '_processed_files', [
            'label' => 'Arquivos do Validador Financeiro Processados',
            'type' => 'json',
            'private' => true,
            'default_value' => '{}'
        ]);

        $this->registerRegistrationMetadata($slug . '_filename', [
            'label' => 'Nome do arquivo de retorno do validador financeiro',
            'type' => 'json',
            'private' => true,
            'default_value' => '[]'
        ]);

        $this->registerRegistrationMetadata($slug . '_raw', [
            'label' => 'Validador Financeiro raw data (csv row)',
            'type' => 'json',
            'private' => true,
            'default_value' => '{}'
        ]);

        $this->registerRegistrationMetadata($slug . '_processed', [
            'label' => 'Validador Financeiro processed data',
            'type' => 'json',
            'private' => true,
            'default_value' => '{}'
        ]);

        $file_group_definition = new \MapasCulturais\Definitions\FileGroup($slug, ['^text/csv$'], 'O arquivo enviado não é um csv.',false,null,true);
        $app->registerFileGroup('opportunity', $file_group_definition);

        parent::register();

        $app->controller($slug)->plugin = $this;
    }

    function getName(): string
    {
        return 'Validador de Recursos';
    }

    function getSlug(): string
    {
        return 'recurso';
    }

    function getControllerClassname(): string
    {
        return Controller::class;
    }

    function isRegistrationEligible(Registration $registration): bool
    {
        return true;
    }
}
