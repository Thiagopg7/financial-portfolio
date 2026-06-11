# Financial Portfolio

Aplicação Laravel 13 + Inertia + React 19, com ambiente de desenvolvimento em Docker
(Nginx + PHP-FPM 8.5 + MySQL 8.4 + Vite).

## Requisitos

- [Docker](https://docs.docker.com/get-docker/) e Docker Compose
- [gitleaks](https://github.com/gitleaks/gitleaks/releases) (para o hook de pré-commit)

## Subindo o projeto

```bash
# 1. Clonar
git clone git@github.com:Thiagopg7/financial-portfolio.git
cd financial-portfolio

# 2. Criar o .env a partir do exemplo
cp .env.example .env

# 3. Definir a senha do banco (o MySQL do container usa o mesmo valor).
#    Use a senha que quiser; precisa ser não-vazia.
sed -i 's/^DB_PASSWORD=.*/DB_PASSWORD=secret/' .env

# 4. (Opcional) Casar o UID/GID do container com o seu usuário
echo "UID=$(id -u)" >> .env
echo "GID=$(id -g)" >> .env

# 5. Subir os containers (a primeira vez compila a imagem e instala dependências)
docker compose up -d --build

# 6. Gerar a APP_KEY e rodar as migrations
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
```

Acesse **http://localhost:8000**.

> O Vite (assets/HMR) sobe junto no container `node` e leva alguns segundos para
> ficar pronto na primeira vez. Acompanhe com `docker compose logs -f node`.

## Serviços

| Serviço | Imagem / Build | Porta (host) | Papel |
|---------|----------------|--------------|-------|
| `web`   | `nginx:1.27-alpine` | `8000` | Entrada HTTP; serve `public/` e faz `fastcgi_pass app:9000` |
| `app`   | `docker/php/Dockerfile` (`php:8.5-fpm`) | — | PHP-FPM (artisan, filas) |
| `db`    | `mysql:8.4` | `3306` | Banco; dados no volume `mysqldata` |
| `node`  | mesmo Dockerfile do `app` | `5173` | Vite + HMR |

### Acesso ao banco por um cliente (DBeaver, Workbench, ...)

- **Host:** `127.0.0.1` · **Porta:** `3306`
- **Banco:** `financial_portfolio` · **Usuário:** `laravel` · **Senha:** a do `DB_PASSWORD` do seu `.env`
- Para acesso total, use `root` com a mesma senha.

## Comandos do dia a dia

```bash
docker compose up -d                              # sobe tudo
docker compose down                               # para (mantém o volume do MySQL)
docker compose down -v                            # para e APAGA os dados do banco

docker compose exec app php artisan <cmd>         # artisan
docker compose exec app php artisan test --compact
docker compose exec app composer <cmd>            # composer

docker compose logs -f node                       # logs do Vite
docker compose logs -f app                        # logs do PHP-FPM

docker compose build                              # rebuild após mudar o Dockerfile
```

## Hook de pré-commit (gitleaks)

O hook em `.githooks/` bloqueia commits com segredos. Em cada máquina nova:

```bash
git config core.hooksPath .githooks   # ou: composer hooks:install
```

E instale o binário do `gitleaks` (ver [releases](https://github.com/gitleaks/gitleaks/releases)).

## Problemas comuns

- **`502 Bad Gateway` no `:8000`** — o Nginx cacheou um IP antigo do `app` (ocorre
  após recriar só esse container). Resolva com `docker compose restart web`.
- **Tela sem estilo / "Vite manifest not found"** — o Vite ainda não subiu; aguarde
  ou verifique `docker compose logs node`.
- **Porta `3306` em uso** — já existe um MySQL local. Troque para `3307:3306` no
  `docker-compose.yml`.
