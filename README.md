## **Linux:**

#### 1. install docker:
```bash
curl -fsSL https://get.docker.com -o get-docker.sh
sudo sh get-docker.sh
```

#### 2. install git:
```bash
sudo apt update && sudo apt install git -y
git config --global user.name "Ваше Ім'я"
git config --global user.email "your_email@example.com"
```

#### 3. сlone the repository and navigate to the folder:
```bash
git clone https://github.com/liubava333/digital_wallet
cd digital_wallet
```

#### 4. install PHP dependencies (Composer) via Docker:
```bash
  docker run --rm \
-u "$(id -u):$(id -g)" \
-v "$(pwd):/var/www/html" \
-w /var/www/html \
laravelsail/php83-cli:latest \
composer install --ignore-platform-reqs
```

#### 5. сreate the .env configuration file:
```bash
cp .env.example .env
```
#### ensure the connection configuration is set as follows:
```bash
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=digital_wallet
DB_USERNAME=sail
DB_PASSWORD=password
```

#### 6. start Laravel Sail containers:
```bash
./vendor/bin/sail up -d
```

#### 7. configure the Laravel application (Key generation and Migrations):
```bash
sail artisan key:generate
sail artisan migrate
```

#### 8. install frontend dependencies and start the development server:
```bash
sail npm install
sail npm run dev
```


## **Windows:**

#### 1. install WSL 2 and Ubuntu:
```bash
wsl --install
```

#### 2. restart your computer if prompted.

#### 3. install Docker Desktop:
- Download and install Docker Desktop for Windows (https://www.docker.com/products/docker-desktop/)
- During installation, ensure the "Use the WSL 2 based engine" option is checked.
- Open Docker Desktop settings (Settings > Resources > WSL Integration), enable integration for your default Ubuntu distro, and click Apply & restart.

#### 4. open your Linux Terminal.

#### 5. continue run steps of 'Linux' from step 2.

---------------------------------------------------------------------------------------------------------
### **Populating the Database with Test Data**

#### 1. open database/seeders/DatabaseSeeder.php and register user creation and run the subscription tiers seeder there:
```bash
<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Створюємо одного тестового користувача для входу з налаштованим тарифом
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'), // пароль для входу
            'tariff_price' => 150.00,          // Стоимость выбранного тарифа
            'balance' => 0.00,                // Стартовый баланс
            'amount_to_pay' => 150.00,         // Изначально нужно оплатить полную стоимость
            'is_paid' => 0,                   // Еще не оплачено
        ]);

        // Запускаємо сидер тарифних планів
        $this->call([
            SubscriptionTierSeeder::class,
        ]);
    }
}
```

#### 2. make seed:
```bash
./vendor/bin/sail artisan db:seed
```

#### 3. add funds:
```bash
./vendor/bin/sail artisan tinker
$user = \App\Models\User::find(1);

// Пополняем виртуальный баланс на 50.00
$user->deposit(50.00);

// Проверяем, как отработала математика:
echo $user->balance;       // Выведет: 50.00
echo $user->amount_to_pay; // Выведет: 100.00 (150.00 - 50.00)
```

#### 4. Pay for tariff
In test mode:
Run the following command (replace with your actual STRIPE_API_KEY):
```bash
docker compose run --rm -e STRIPE_API_KEY=sk_test_51T*** stripe listen --forward-to laravel.test/api/webhook
```
The command will generate a STRIPE_WEBHOOK_SECRET (e.g., whsec_4ef7f67***). Copy and paste this value 
into your .env file and save it.Go to the 'Balance' page and complete the payment.

#### 5. automatically clean up expired or outdated additional cells:
```bash
./vendor/bin/sail artisan additionalCells:clean
```
--------------------------------------------------------------------------------
Full-stack веб-приложение на базе архитектуры SPA (Single Page Application), построенное по монолитной схеме без
необходимости создания отдельного API.

## 🧱 Основной стек (Backend & Frontend)

#### 1. PHP 8.3 & Laravel 13: 
Самая актуальная и производительная версия языка PHP и фреймворка Laravel. Backend отвечает за бизнес-логику, 
безопасность, работу с базой данных (ORM Eloquent), маршрутизацию и очереди.
#### 2. Vue 3 (Composition API) & TypeScript: 
Фронтенд-фреймворк для создания быстрого и реактивного пользовательского интерфейса. Использование TypeScript 
гарантирует строгую типизацию данных и минимизирует ошибки при разработке.
#### 3. Inertia.js (v2.0):
Главный «мост» между Laravel и Vue. Он позволяет строить классическое SPA (одностраничное приложение)
без написания отдельного REST API или GraphQL. Вы просто возвращаете Vue-компоненты прямо из контроллеров
Laravel (Inertia::render).
#### 4. Vite 8 & Laravel Vite Plugin:
Сверхбыстрый сборщик фронтенда, обеспечивающий моментальную горячую перезагрузку (HMR) при разработке и эффективную
компиляцию файлов для продакшена.

## 🎨 Стилизация и UI компоненты

#### 1. Tailwind CSS (v3 / v4):
Утилитарный CSS-фреймворк для быстрой верстки интерфейсов прямо в HTML/Vue коде.
#### 2. Bootstrap 5 & Sass:
Присутствуют в зависимостях наряду с Tailwind. Вероятно, используются готовые компоненты или legacy-стили.
#### 3. DayPilot Lite (@daypilot/daypilot-lite-vue): 
Специализированная библиотека для создания интерактивных календарей, расписаний и планировщиков задач во Vue 3.
#### 4. IMask:
Библиотека для создания масок ввода в инпутах (например, для правильного форматирования номеров телефонов или дат).

## 💳 Платежи, Безопасность и ИнтеграцииStripe PHP:

#### 1. Официальный SDK для интеграции с платежной системой Stripe. 
Используется для обработки подписок, разовых платежей и работы с вебхуками.
#### 2. Laravel Sanctum:
Система аутентификации. Обеспечивает легкую и безопасную авторизацию пользователей через куки/сессии для SPA,
а также поддерживает API-токены.
#### 3. Ziggy:
Пакет, который позволяет использовать именованные роуты Laravel прямо внутри JavaScript/Vue файлов через удобную 
функцию route('name').
#### 4. Axios: 
HTTP-клиент для выполнения AJAX-запросов из Vue на бэкенд (например, отправка форм без перезагрузки страницы).

## 🛠️ Инструменты разработки (Development & DevOps)

#### 1. Laravel Sail:
Среда разработки на базе Docker. Позволяет запускать проект со всеми зависимостями (MySQL, Redis и т.д.)
одной командой без установки PHP на локальный компьютер.
#### 2. Laravel Breeze:
Легковесный стартовый пакет, который автоматически развернул базовую систему аутентификации (вход, регистрация,
сброс пароля) на стеке Vue + Inertia.
#### 3. Laravel Pail:
Современный инструмент для удобного стриминга и просмотра логов приложения прямо в терминале в реальном времени.
#### 4. Laravel Tinker:
Интерактивная консоль (REPL) для выполнения PHP-кода в контексте вашего приложения (именно через неё вы тестируете 
пополнение баланса).
#### 5. Concurrently:
Пакет Node.js, который позволяет одной командой npm run dev параллельно запускать сервер Laravel,
обработчик очередей (queue:listen), логгер pail и сборщик vite.
