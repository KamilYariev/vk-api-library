# Как опубликовать библиотеку на GitHub

1. Скачайте архив `vk-api-library.zip` и распакуйте его.
2. Войдите на https://github.com/new и создайте репозиторий:
   - Repository name: `vk-api-library`
   - Visibility: **Public**
   - не добавляйте README, `.gitignore` или License на GitHub — они уже есть в проекте.
3. Откройте PowerShell в распакованной папке `vk-api-library` (внутри должны быть `composer.json`, `README.md`, `src`, `tests`).
4. Выполните команды, заменив `<YOUR_GITHUB_USERNAME>` на имя своего GitHub-аккаунта:

```powershell
git init
git add .
git commit -m "Initial implementation of VK API PHP library"
git branch -M main
git remote add origin https://github.com/<YOUR_GITHUB_USERNAME>/vk-api-library.git
git push -u origin main
```

5. Если Git впервые используется на компьютере, задайте имя и почту для коммитов:

```powershell
git config --global user.name "Ваше имя"
git config --global user.email "почта, указанная в GitHub"
```

После этого повторите `git commit` и `git push`. При запросе авторизации пройдите вход в GitHub через предложенное окно/менеджер учётных данных. Не вставляйте пароль или токен в команды.

6. После отправки откройте `https://github.com/<YOUR_GITHUB_USERNAME>/vk-api-library`. Ссылка на эту страницу и есть ссылка на решение для формы отбора.

## Проверка

- В репозитории должны быть видны `src/`, `tests/`, `.github/workflows/tests.yml`, `composer.json` и `README.md`.
- После `git push` откройте вкладку **Actions**: workflow `PHP tests` должен запуститься автоматически. Он устанавливает зависимости через Composer и выполняет PHPUnit.
- Если workflow завершился ошибкой, откройте его запуск и изучите лог шага `Run PHPUnit`.

## Важно

- Не добавляйте настоящий `VK_ACCESS_TOKEN` в репозиторий. `.env` уже добавлен в `.gitignore`.
- `composer require miem/vk-api-library` будет работать после публикации пакета в Packagist. Пока библиотека не опубликована там, клонируйте свой GitHub-репозиторий и выполняйте `composer install`.
- Публичный репозиторий означает, что исходный код виден всем. Токены и другие секреты не должны входить в архив или коммиты.
