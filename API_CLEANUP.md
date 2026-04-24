# Limpeza da Aplicação Laravel ? API Pura

## Resumo das Mudanças

Esta aplicação foi transformada em uma **API pura** que retorna apenas respostas JSON. Todas as dependências de frontend foram removidas.

## O que foi removido

- ? **`resources/`** - Pasta contendo views, CSS e JavaScript
- ? **`node_modules/`** - Dependências npm (Vite, Tailwind CSS)
- ? **`public/build/`** - Artefatos de build do Vite
- ? **Dependências de Frontend** no `package.json`:
  - `@tailwindcss/vite`
  - `laravel-vite-plugin`
  - `tailwindcss`
  - `vite`
  - `concurrently`

## O que foi modificado

### 1. **`routes/web.php`**
   - Removida a rota welcome que retornava view
   - Mantida apenas como arquivo vazio para compatibilidade

### 2. **`routes/api.php`** (NOVO)
   - Criado arquivo para rotas da API
   - Prefixo `/api` aplicado automaticamente

### 3. **`bootstrap/app.php`**
   - Removida referência a `routes/web.php`
   - Adicionada referência a `routes/api.php`
   - Configurado `shouldRenderJsonWhen()` para SEMPRE retornar JSON em exceções

### 4. **`package.json`**
   - Simplificado: removidos scripts de build e dependências
   - Mantém apenas estrutura básica

### 5. **`vite.config.js`**
   - Arquivo limpo: comentado indicando que não é necessário para API pura

### 6. **`composer.json`**
   - Descrição atualizada para "API-only Laravel application"
   - Scripts de setup simplificados (removidos npm install/build)
   - Scripts dev simplificados (apenas `php artisan serve`)

## Como usar

### Inicializar a aplicação
```bash
composer install
php artisan key:generate
php artisan migrate
php artisan serve
```

### Criando rotas da API
Adicione suas rotas em `routes/api.php`:

```php
Route::apiResource('users', UserController::class);
Route::get('/status', function () {
    return response()->json(['status' => 'ok']);
});
```

### Retornos JSON automáticos
- Todos os erros/exceções retornam JSON automaticamente
- Modelos Eloquent são convertidos para JSON automaticamente
- Use `Response::json()` ou simplesmente retorne dados

## Estrutura de diretórios

```
??? app/
?   ??? Http/
?   ?   ??? Controllers/
?   ??? Models/
??? database/
?   ??? factories/
?   ??? migrations/
?   ??? seeders/
??? routes/
?   ??? api.php          ? Suas rotas de API aqui
?   ??? web.php          ? Vazio (compatibilidade)
?   ??? console.php
??? tests/
??? public/              ? Apenas arquivos estáticos
??? storage/
```

## Validação de Código

Antes de fazer commits, execute o Pint para formatar o código:

```bash
vendor/bin/pint --format agent
```

## Próximas etapas

1. ? Aplicação limpa e pronta para ser API pura
2. ?? Adicione seus controllers de API em `app/Http/Controllers/`
3. ??? Crie suas rotas em `routes/api.php`
4. ?? Escreva testes em `tests/Feature/`
5. ?? Deploy com confiança

---

**Nota**: Esta aplicação está totalmente focada em ser uma API. Não mantenha views ou assets CSS/JS.
