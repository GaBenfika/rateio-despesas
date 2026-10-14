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
- **Despesas:** criação em BRL (rateio imediato) e em USD (fica pendente e vai para a fila).
- **Câmbio:** conversão com cotação simulada, fim de semana usando a cotação do último dia útil e API fora do ar sem perder a despesa.
- **CSV:** importação parcial com relatório de erros por linha, arquivo salvo pelo Excel (BOM) e linhas em branco.
- **API:** autenticação (401 sem token), criação, validação (422), 404 para registro inexistente, importação e relatório.
- **Telas:** redirecionamento para o login, carregamento das páginas e cadastro de despesa pelo formulário.

A API de câmbio não é chamada de verdade nos testes: as respostas são simuladas com `Http::fake()`.

---

## API

Todas as rotas usam o prefixo `/api`. Exceto o login, todas exigem o cabeçalho `Authorization: Bearer {token}`.

| Método | Rota | Descrição |
|---|---|---|
| `POST` | `/login` | Devolve o token de acesso |
| `GET` | `/units` | Lista as unidades com a empresa |
| `POST` | `/units` | Cadastra uma unidade |
| `GET` | `/expenses` | Lista as despesas (filtros opcionais: `from`, `to`, `status`, `currency`) |
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

> _Seção a ser preenchida._

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
