# Consulta empresas na ANVISA

Consulta empresas na ANVISA utilizando a seguinte URL:

```
https://consultas.anvisa.gov.br/#/empresas/empresas/q/?cnpj=<cnpj>
```

```bash
composer require librecodecoop/consulta-empresa-anvisa
```

Forma de uso:

```php
$consulta = new Consulta();
var_dump($consulta->processaLista([12345678901234]));
```
