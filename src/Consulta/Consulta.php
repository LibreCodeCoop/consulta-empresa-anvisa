<?php

namespace ConsultaEmpresa\Consulta;

use Bissolli\ValidadorCpfCnpj\Documento;
use Goutte\Client;

class Consulta
{
    private $validList = [];
    private $invalidList = [];
    /**
     * @var Client
     */
    private $client;
    private $output = [];

    /**
     * Processa uma lista de CNPJ e retorna todos os dados destes CNPJ
     *
     * @param array $list
     * @throws \Exception
     * @return array
     */
    public function processaLista(array $list): array
    {
        $this->validateCnpj($list);
        if ($this->invalidList) {
            throw new \Exception('Invalid: '.implode(',', $this->invalidList));
        }
        $this->client = new Client();
        foreach ($this->validList as $cnpj) {
            $empresa = $this->getNomeFantasia($cnpj);
            $empresa['funcionamento'] = $this->consultaFuncionamento($cnpj);
            foreach ($empresa['funcionamento'] as $key => $processo) {
                $response = $this->consultaProcesso($processo['numeroProcesso']);
                $empresa['funcionamento'][$key] = array_merge(
                    $empresa['funcionamento'][$key],
                    $response
                );
            }
            $this->output[$cnpj] = $empresa;
        }
        return $this->output;
    }

    /**
     * Consulta informações de funcionamento da empresa
     *
     * @param string $cnpj
     * @throws \Exception
     * @return array
     */
    private function consultaFuncionamento(string $cnpj): array
    {
        $funcionamento = [];
        $this->client->setHeader('Authorization', 'Guest');
        $page = 1;
        do {
            $this->client->request(
                'GET',
                'https://consultas.anvisa.gov.br/api/empresa/funcionamento?' .
                http_build_query([
                    'count' => 100,
                    'filter[cnpj]' => $cnpj,
                    'page' => $page
                ])
            );
            $content = json_decode($this->client->getResponse()->getContent(), true);
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
     * @param string $cnpj
     * @throws \Exception
     * @return array
     */
    private function getNomeFantasia(string $cnpj): array
    {
        $this->client->setHeader('Authorization', 'Guest');
        $this->client->request('GET', 'https://consultas.anvisa.gov.br/api/empresa/' . $cnpj);
        $content = json_decode($this->client->getResponse()->getContent(), true);
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
    private function consultaProcesso(string $processo): array
    {
        $this->client->setHeader('Authorization', 'Guest');
        $this->client->request('GET', 'https://consultas.anvisa.gov.br/api/empresa/funcionamento/' . $processo);
        $content = json_decode($this->client->getResponse()->getContent(), true);
        if (!$content) {
            throw new \Exception('Invalid response in ' . __FUNCTION__);
        }
        if (isset($content['error'])) {
            throw new \Exception($content['error']);
        }
        return $content;
    }

    /**
     * Valida CNPJ e atribui a lista de CNPJ válido e inválido para
     * $this->invalidList e $this->validList
     *
     * @param array $lista
     * @return null
     */
    private function validateCnpj(array $lista)
    {
        foreach ($lista as $cnpj) {
            $document = new Documento($cnpj);
            if (!$document->isValid()) {
                $this->invalidList[] = $cnpj;
            } else {
                $this->validList[] = $document->getValue();
            }
        }
    }
}
