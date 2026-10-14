# Rateio de Despesas

Sistema para ratear despesas compartilhadas entre as unidades de um grupo de empresas, substituindo a planilha usada hoje.

Cada despesa (ferramentas, serviços, pessoas) é dividida entre unidades por percentual. Despesas em dólar são convertidas para real com a cotação da data da despesa, e o sistema gera o total em BRL de cada unidade por período.

**Stack:** Laravel 13 · PHP 8.4+ · MySQL 8 · Blade + Pico CSS · Laravel Sanctum · Docker (Laravel Sail)

---

## Funcionalidades

| Requisito | Onde |
|---|---|
| Cadastro de unidades (nome e empresa) | API e tela **Unidades** |
| Cadastro de despesas com rateio em % (soma exatamente 100%, centavos fecham com o total) | API e tela **Nova despesa** |
| Conversão USD → BRL pela cotação da data da despesa, sem perder a despesa se a API de câmbio cair | Fila com nova tentativa automática |
| Importação por CSV com relatório de erros por linha | API e tela **Importar CSV** |
| Relatório de total em BRL por unidade no período | API e tela **Total por unidade** |
| Autenticação na API (diferencial) | Laravel Sanctum (token Bearer) |
| Conversão de câmbio via fila com retry (diferencial) | Job com 5 tentativas e espera crescente |

---

## Como rodar

Pré-requisitos: **Docker** e **Git**. Não é preciso ter PHP nem Composer instalados.

```bash
# 1. Clonar o projeto
git clone https://github.com/GaBenfika/rateio-despesas.git
cd rateio-despesas

# 2. Criar o arquivo de ambiente
cp .env.example .env

# 3. Instalar as dependências do PHP via Docker (a pasta vendor não vai para o Git)
docker run --rm -u "$(id -u):$(id -g)" -v "$(pwd):/var/www/html" -w /var/www/html \
    laravelsail/php84-composer:latest composer install --ignore-platform-reqs

# 4. Subir os containers (aplicação, MySQL e worker da fila)
./vendor/bin/sail up -d

# 5. Gerar a chave da aplicação e criar o banco com os dados iniciais
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate --seed

# 6. Reiniciar o worker da fila para ele carregar a chave e o banco já criados
./vendor/bin/sail restart queue
```

> Na primeira vez, o MySQL leva alguns segundos para inicializar. Se o `migrate` acusar `Connection refused`, aguarde e rode de novo.

Acesse **http://localhost** e entre com o usuário criado pelo seeder:

| E-mail | Senha |
|---|---|
| `benficaga04@gmail.com` | `password` |

Para criar um usuário com seu próprio e-mail e senha:

```bash
./vendor/bin/sail artisan user:create
```

O comando pergunta nome, e-mail e senha (mínimo de 8 caracteres). O usuário criado vale para o login na tela e na API.

O mesmo usuário serve para o login na API.

O seeder também cria 2 empresas e 3 unidades (`unidade-a`, `unidade-b` e `unidade-c`), que são os códigos usados no CSV de exemplo.

### Worker da fila

O `sail up -d` sobe também o serviço **queue**, que roda o worker responsável por converter as despesas em USD. Sem ele, essas despesas ficam como "Aguardando cotação".

```bash
./vendor/bin/sail logs -f queue      # acompanhar as conversões
./vendor/bin/sail restart queue      # necessário após alterar código ou .env
```

### Dica: alias do Sail

```bash
alias sail='./vendor/bin/sail'
```

### Acesso ao banco (opcional)

Para conectar um cliente como o MySQL Workbench ou o DBeaver:

| Host | Porta | Usuário | Senha | Banco |
|---|---|---|---|---|
| `127.0.0.1` | valor de `FORWARD_DB_PORT` no `.env` (padrão 3306) | `sail` | `password` | `rateio_despesas` |

---

## Testes

```bash
./vendor/bin/sail artisan test
```

Os testes cobrem as regras principais:

- **Rateio:** divisão de centavos pelo método do maior resto (R$ 100,00 em 33,33% / 33,33% / 33,34% fecha exatamente o total), empate, soma diferente de 100% e unidade repetida.
- **Despesas:** criação em BRL (rateio imediato) e em USD (fica pendente e vai para a fila), e rejeição de data futura.
- **Câmbio:** conversão com cotação simulada, fim de semana usando a cotação do último dia útil e API fora do ar sem perder a despesa.
- **CSV:** importação parcial com relatório de erros por linha, arquivo salvo pelo Excel (BOM) e linhas em branco.
- **API:** autenticação (401 sem token), criação, validação (422), 404 para registro inexistente, importação e relatório.
- **Telas:** redirecionamento para o login, carregamento das páginas e cadastro de despesa pelo formulário.
- **Comando `user:create`:** criação do usuário com a senha em hash e recusa de e-mail inválido ou já cadastrado e de senha curta.

A API de câmbio não é chamada de verdade nos testes: as respostas são simuladas com `Http::fake()`.

---

## API

Todas as rotas usam o prefixo `/api`. Exceto o login, todas exigem o cabeçalho `Authorization: Bearer {token}`.

| Método | Rota | Descrição |
|---|---|---|
| `POST` | `/login` | Devolve o token de acesso |
| `GET` | `/units` | Lista as unidades com a empresa |
| `POST` | `/units` | Cadastra uma unidade |
| `GET` | `/expenses` | Lista as despesas, mais recentes primeiro (20 por página, parâmetro `page`) |
| `POST` | `/expenses` | Cadastra uma despesa com rateio |
| `GET` | `/expenses/{id}` | Mostra uma despesa com o rateio |
| `DELETE` | `/expenses/{id}` | Exclui uma despesa |
| `POST` | `/expenses/import` | Importa despesas por CSV (`multipart/form-data`, campo `file`) |
| `GET` | `/reports/units?from=AAAA-MM-DD&to=AAAA-MM-DD` | Total em BRL por unidade no período |

### Exemplos

**Login**

```json
POST /api/login
{ "email": "benficaga04@gmail.com", "password": "password" }
```

**Cadastrar despesa**

```json
POST /api/expenses
{
  "description": "Licença CRM",
  "supplier": "Fornecedor X",
  "expense_date": "2026-09-01",
  "currency": "USD",
  "amount": "1500.00",
  "allocations": [
    { "unit_id": 1, "percentage": "50" },
    { "unit_id": 2, "percentage": "30" },
    { "unit_id": 3, "percentage": "20" }
  ]
}
```

- Valores monetários são enviados como texto com até 2 casas decimais (`"1500.00"`) e devolvidos em **centavos** (`amount_cents`, `amount_brl_cents`).
- Percentuais aceitam até 2 casas decimais. Três partes iguais devem ser lançadas como `33.33`, `33.33` e `33.34`.
- Despesas em USD são criadas com `status: "pending"` e passam a `converted` quando o worker obtém a cotação.

**Relatório**

```json
GET /api/reports/units?from=2026-09-01&to=2026-09-30
{
  "from": "2026-09-01",
  "to": "2026-09-30",
  "pending_expenses": 0,
  "units": [
    { "unit_id": 1, "unit": "Unidade A", "code": "unidade-a", "total_brl_cents": 389918, "total_brl": "3899.18" }
  ]
}
```

`pending_expenses` indica quantas despesas do período ainda aguardam cotação e por isso não entram no total.

### Coleção do Postman

Em `docs/Rateio de Despesas.postman_collection.json`. Importe no Postman e faça o login: o token é salvo automaticamente na variável `token` da coleção e usado nas demais requisições.

---

## Importação por CSV

Formato (separador `;`, com cabeçalho):

```
data;descricao;fornecedor;valor;moeda;rateio
2026-09-01;Licença CRM;Fornecedor X;1500.00;USD;unidade-a:50|unidade-b:30|unidade-c:20
```

- `rateio`: pares `codigo-da-unidade:percentual` separados por `|`.
- Cada linha é validada e gravada de forma independente: linhas inválidas não impedem a importação das válidas.
- A resposta traz o total importado e os erros com o número da linha:

```json
{
  "imported": 3,
  "errors": [
    { "line": 4, "messages": ["A soma do rateio precisa ser exatamente 100% (recebido: 90%)."] },
    { "line": 5, "messages": ["Unidade \"unidade-x\" não encontrada."] },
    { "line": 6, "messages": ["Esperadas 6 colunas separadas por \";\", recebidas 3."] }
  ]
}
```

Há um arquivo de exemplo, com linhas válidas e inválidas, em `docs/exemplo-importacao.csv`.

---

## Estrutura do projeto

```
app/
├── Actions/
│   ├── CreateExpense.php         Ponto único de criação de despesa (API, tela e CSV): valida e grava
│   └── ApplyExchangeRate.php     Converte para BRL e faz o rateio em centavos
├── Services/
│   ├── AllocationSplitter.php    Divide um valor em centavos pelo método do maior resto
│   ├── ExchangeRateClient.php    Consulta a cotação USD → BRL (Frankfurter)
│   ├── CsvExpenseImporter.php    Lê o CSV linha a linha e monta o relatório de erros
│   └── UnitReport.php            Total em BRL por unidade no período
├── Jobs/                         Conversão de câmbio na fila, com nova tentativa automática
├── Http/Controllers/
│   ├── Api/                      Respostas em JSON
│   └── Web/                      Telas em Blade
└── Models/                       Company, Unit, Expense, ExpenseAllocation
```

Os controllers só recebem a requisição e chamam as Actions e Services. A regra de negócio fica em um lugar só, seja qual for a porta de entrada (API, tela ou CSV).

### Banco de dados

| Tabela | Conteúdo |
|---|---|
| `companies` | Empresas do grupo |
| `units` | Unidades, com a empresa e o código usado no CSV |
| `expenses` | Despesas: valor original em centavos, moeda, cotação usada, valor em BRL e status |
| `expense_allocations` | Rateio: percentual de cada unidade e valor em BRL em centavos |

---

## Decisões técnicas

**1. Arquitetura simples, com a regra em um lugar só.**
Sem Repository, DTO ou camadas extras: controllers finos chamando Actions e Services. Toda despesa nasce pela `CreateExpense`, seja pela API, pela tela ou pelo CSV. Assim a validação e o rateio não podem divergir entre as portas de entrada, e há um único lugar para testar e alterar.

**2. Dinheiro em centavos e percentual em pontos-base.**
Valores são gravados como inteiros em centavos (`amount_cents`, `amount_brl_cents`) e percentuais em pontos-base (`percentage_bp`, 10000 = 100%). Com inteiros não há erro de arredondamento de ponto flutuante, e "a soma precisa ser 100%" vira uma comparação exata (`=== 10000`, na constante `AllocationSplitter::ONE_HUNDRED_PERCENT`).

**3. Rateio pelo método do maior resto, feito em BRL.**
Cada unidade recebe a sua parte arredondada para baixo, e os centavos que sobram vão, um a um, para as maiores frações descartadas. A soma das partes sempre fecha exatamente com o total (R$ 100,00 em 33,33% / 33,33% / 33,34% não perde nem cria centavo). O rateio é feito sobre o valor já convertido em BRL, e não convertendo cada parte em dólar, para que o total por unidade feche com o valor em reais da despesa.

**4. Câmbio na fila.**
- A conversão USD → BRL roda no job `ConvertExpenseToBrl`, fora da requisição: se a API de câmbio estiver lenta ou fora do ar, o cadastro não falha e a despesa fica salva como `pending`.
- **Nova tentativa com espera crescente:** até 5 tentativas, aguardando 1, 5 e 15 minutos. Esgotadas, a despesa continua salva, o job fica em `failed_jobs` e pode ser reprocessado com `queue:retry`.
- **`afterCommit`:** o job só é enfileirado depois que a transação que criou a despesa é confirmada, para o worker nunca buscar uma despesa que ainda não existe no banco.
- **Idempotência:** o job ignora despesas que não estão mais pendentes, então um job duplicado ou reprocessado não converte duas vezes.
- **Worker no Docker:** o serviço `queue` do `compose.yaml` sobe junto com o `sail up -d` e reinicia sozinho se cair, então ninguém precisa lembrar de rodar `queue:work`.

**5. Frankfurter, fim de semana e `exchange_rate_date`.**
A Frankfurter é gratuita, não exige chave e, para fins de semana e feriados, devolve a cotação do último dia útil. A data efetivamente usada é gravada em `exchange_rate_date`, separada da `expense_date`, junto com a cotação (`exchange_rate`). Isso permite auditar qualquer conversão depois. A URL é configurável (`EXCHANGE_RATE_URL`) e trocar de fonte afeta apenas o `ExchangeRateClient`.

**6. Importação CSV linha a linha.**
Cada linha passa pela mesma `CreateExpense` e é gravada na sua própria transação: uma linha inválida não impede as válidas, e o retorno lista os erros com o número da linha. As unidades são carregadas uma vez só, e o importador aceita o BOM que o Excel adiciona e ignora linhas em branco.

**7. Relatório somando o que foi gravado no rateio.**
O total por unidade é a soma de `expense_allocations.amount_brl_cents`, ou seja, exatamente os centavos atribuídos no rateio, sem recalcular percentuais na consulta. Despesas ainda sem cotação não entram na soma, mas são informadas em `pending_expenses`, para quem lê o relatório saber que o total ainda pode mudar.

**8. Autenticação na API e também nas telas.**
A API usa Laravel Sanctum com token Bearer, e as telas usam sessão com login. Nenhuma rota de dados fica aberta, em nenhuma das duas portas.

**9. Respostas da API previsíveis.**
Toda rota em `/api` responde em JSON, inclusive erros de validação (422) e de autenticação (401). Um ID inexistente devolve 404 com uma mensagem clara (`Registro não encontrado (id: 99).`) no lugar da página de erro do Laravel. As rotas da API têm o prefixo de nome `api.`, para não colidir com os nomes das rotas das telas.

**10. Validação e limites.**
Valores e percentuais com até 2 casas decimais e maiores que zero, moeda restrita a BRL e USD, data no formato `AAAA-MM-DD` e não futura (não existe cotação para o futuro), unidade existente e sem repetição na mesma despesa (também garantido por índice único no banco), soma do rateio exatamente 100%, CSV de até 2 MB e listagem paginada (20 por página).

**11. Sem edição de despesa.**
Editar exigiria refazer a conversão e o rateio e decidir o que acontece com a cotação já usada. Para corrigir, a despesa é excluída e lançada de novo (as linhas de rateio são apagadas em cascata). Os detalhes estão em "O que ficou de fora e por quê".

**12. Interface simples, sem build de front-end.**
Blade com Pico CSS via CDN e um CSS próprio pequeno. Não há Node, npm nem Vite para instalar ou compilar: o foco do teste é a regra de negócio, e as telas existem para usá-la de ponta a ponta.

**13. Docker com Sail.**
O ambiente inteiro (PHP, MySQL e worker da fila) sobe com `sail up -d`, e só é preciso ter Docker e Git. Quem avalia roda exatamente o mesmo ambiente do desenvolvimento, sem instalar PHP nem MySQL na máquina.

---

## O que ficou de fora e por quê

- **Edição de despesa:** editar exigiria refazer a conversão e o rateio e manter um histórico do que mudou. Para corrigir, a despesa é excluída e lançada novamente. Edição com histórico seria a evolução natural.
- **Cadastro de empresas pela interface:** as empresas são criadas pelo seeder, já que o requisito pede cadastro de unidades e a empresa é apenas o vínculo delas.
- **Permissões por perfil:** há um único tipo de usuário. Perfis (quem lança, quem aprova, quem só consulta) dependem de regras do negócio ainda não definidas.
- **Outras moedas além de BRL e USD:** fora do escopo do enunciado. A estrutura já guarda moeda e cotação por despesa, então a extensão é localizada.
- **Importação de arquivos grandes em segundo plano:** o CSV é processado na hora, o que atende planilhas de centenas de linhas e devolve o relatório imediatamente. Para arquivos grandes, a evolução é processar na fila e consultar o resultado depois.
- **Cotação oficial do Banco Central (PTAX):** foi usada a Frankfurter, que é gratuita, não exige chave e já trata fins de semana e feriados. Trocar de fonte afeta apenas o `ExchangeRateClient`.
- **Reprocessamento automático de conversões que esgotaram as tentativas:** hoje é feito com `sail artisan queue:retry all`. Um agendamento que reenvia as despesas pendentes seria a evolução.

---

## Perguntas para o time de negócio antes de ir para produção

1. **Qual cotação usar?** A PTAX do Banco Central (oficial) ou uma cotação comercial? Compra ou venda?
2. **Qual data vale para a conversão:** a data da despesa, a da nota fiscal ou a do pagamento?
3. **Fins de semana e feriados:** usar a cotação do último dia útil anterior, como hoje, ou a do próximo dia útil?
4. **O centavo que sobra no rateio** vai para a unidade com a maior fração descartada (regra atual). Existe alguma regra preferida, como a unidade principal ou a de maior percentual?
5. **Despesas com data futura** (provisionadas) devem ser aceitas? Hoje não são, porque não existe cotação futura.
6. **Reimportar o mesmo CSV** deve duplicar as despesas ou ignorar as já existentes? Qual seria a chave de duplicidade?
7. **O valor no CSV pode vir com vírgula decimal** (`1500,00`), como o Excel em português costuma gerar? Hoje é aceito apenas ponto, conforme o enunciado.
8. **O rateio de uma despesa pode mudar depois de lançado?** Se sim, é preciso manter histórico?
9. **Quem pode lançar, editar e excluir despesas?** Há aprovação antes de a despesa entrar no relatório?
10. **Existe valor máximo esperado por despesa**, ou algum limite que deva gerar alerta?
