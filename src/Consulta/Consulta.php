<?php

namespace ConsultaEmpresa\Consulta;

use Bissolli\ValidadorCpfCnpj\Documento;
use Goutte\Client;
use GuzzleHttp\Client as GuzzleClient;

class Consulta
{
    /**
     * @var Client
     */
    private $client;
    
    public function __construct(string $cnpj = null)
    {
        if ($cnpj) {
            $this->setCnpj($cnpj);
        }
    }

    /**
     * Retorna todos os dados de um CNPJ
     *
     * @throws \Exception
     * @return array
     */
    public function getResult(): array
    {
        $empresa = $this->getNomeFantasia();
        $empresa['funcionamento'] = $this->consultaFuncionamento();
        foreach ($empresa['funcionamento'] as $key => $processo) {
            $response = $this->consultaProcesso($processo['numeroProcesso']);
            $empresa['funcionamento'][$key] = array_merge(
                $empresa['funcionamento'][$key],
                $response
            );
        }
        return $empresa;
    }
    
    public function setCnpj($cnpj)
    {
        $this->cnpj = $this->sanitize($cnpj);
    }

    public function sanitize($cnpj)
    {
        $document = new Documento($cnpj);
        if (!$document->isValid()) {
            throw new \Exception('Invalid: '. $cnpj);
        }
        return $document->getValue();
    }

    /**
     * Retorna um client Goutte
     *
     * @return Client
     */
    private function getClient(): Client
    {
        if (!$this->client) {
            $this->client = new Client();
            $this->client->setClient(new GuzzleClient(['allow_redirects' => false, 'cookies' => true, 'verify' => false]));
        }
        return $this->client;
    }

    /**
     * Consulta informações de funcionamento da empresa
     *
     * @throws \Exception
     * @return array
     */
    public function consultaFuncionamento(array $filter = []): array
    {
        if (!$filter) {
            $filter = ['filter[cnpj]' => $this->cnpj];
        } elseif (!isset($filter['cnpj'])) {
            $filter['filter[cnpj]'] = $this->cnpj;
        }
        $funcionamento = [];
        $this->getClient()->setHeader('Authorization', 'Guest');
        $page = 1;
        do {
            $this->getClient()->request(
                'GET',
                'https://consultas.anvisa.gov.br/api/empresa/funcionamento?' .
                http_build_query(array_merge(
                    [
                        'count' => 100,
                        'page' => $page
                    ],
                    $filter
                ))
            );
            $content = json_decode($this->getClient()->getResponse()->getContent(), true);
            if (!$content) {
                throw new \Exception('Invalid response in ' . __FUNCTION__);
            }
            if (isset($content['error'])) {
                throw new \Exception($content['error']);
            }
            if (!isset($content['content'])) {
                throw new \Exception('Invalid content in ' . __FUNCTION__);
            }
            $funcionamento = array_merge($funcionamento, $content['content']);
            $page++;
        } while ($content['number'] < $content['totalPages'] -1);
        return $funcionamento;
    }

    /**
     * Consulta o nome fantasia da empresa
     *
     * @throws \Exception
     * @return array
     */
    private function getNomeFantasia(): array
    {
        $this->getClient()->setHeader('Authorization', 'Guest');
        $this->getClient()->request('GET', 'https://consultas.anvisa.gov.br/api/empresa/' . $this->cnpj);
        $content = json_decode($this->getClient()->getResponse()->getContent(), true);
        if (!$content) {
            throw new \Exception('Invalid response in ' . __FUNCTION__);
        }
        if (isset($content['error'])) {
            throw new \Exception($content['error']);
        }
        return $content;
    }

    /**
     * Retorna detalhes dos processos da empresa
     * @param string $processo
     * @throws \Exception
     * @return array
     */
    public function consultaProcesso(string $processo): array
    {
        $this->getClient()->setHeader('Authorization', 'Guest');
        $this->getClient()->request('GET', 'https://consultas.anvisa.gov.br/api/empresa/funcionamento/' . $processo);
        $content = json_decode($this->getClient()->getResponse()->getContent(), true);
        if (!$content) {
            throw new \Exception('Invalid response in ' . __FUNCTION__);
        }
        if (isset($content['error'])) {
            throw new \Exception($content['error']);
        }
        return $content;
    }
}
