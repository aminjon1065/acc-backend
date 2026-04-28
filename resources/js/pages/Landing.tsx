import { Head } from '@inertiajs/react'
import { Link } from '@inertiajs/react'
import { login, dashboard } from '@/routes'
import { usePage } from '@inertiajs/react'
import { Button } from '@/components/ui/button'
import {
  Package,
  TrendingUp,
  Users,
  Shield,
  CheckCircle,
  Zap,
  BarChart3,
  CreditCard,
} from 'lucide-react'

const features = [
  {
    icon: Package,
    title: 'Управление товарами',
    desc: 'Полный контроль над складом: остатки, движение, себестоимость',
  },
  {
    icon: TrendingUp,
    title: 'Продажи',
    desc: 'Быстрое оформление продаж с поддержкой товаров и услуг',
  },
  {
    icon: BarChart3,
    title: 'Аналитика',
    desc: 'Доходы, расходы, прибыль — вся информация на одном экране',
  },
  {
    icon: CreditCard,
    title: 'Долги',
    desc: 'Дебиторская и кредиторская задолженность под контролем',
  },
  {
    icon: Users,
    title: 'Мульти-пользователи',
    desc: 'Роли: продавец, владелец, суперадмин — разграничение прав',
  },
  {
    icon: Shield,
    title: 'Безопасность',
    desc: ' Sanctum аутентификация, двухфакторная защита',
  },
]

const stats = [
  { value: '99.9%', label: 'Uptime' },
  { value: '<50ms', label: 'Response Time' },
  { value: 'REST', label: 'API' },
]

export default function Landing() {
  const { auth } = usePage().props as { auth: { user: unknown } }

  return (
    <>
      <Head title="ckaccounting API — Backend for Store Accounting" />

      <div className="min-h-screen bg-background">
        {/* Navigation */}
        <header className="fixed top-0 z-50 w-full border-b bg-background/80 backdrop-blur-sm">
          <div className="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
            <div className="flex items-center gap-2">
              <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-primary text-primary-foreground">
                <span className="font-bold text-sm">CK</span>
              </div>
              <span className="font-semibold text-lg">ckaccounting</span>
            </div>

            <nav className="hidden md:flex items-center gap-6">
              <a href="#features" className="text-sm text-muted-foreground hover:text-foreground transition-colors">
                Возможности
              </a>
              <a href="#api" className="text-sm text-muted-foreground hover:text-foreground transition-colors">
                API
              </a>
              <a href="#docs" className="text-sm text-muted-foreground hover:text-foreground transition-colors">
                Документация
              </a>
            </nav>

            <div className="flex items-center gap-3">
              {auth.user ? (
                <Link href={dashboard()}>
                  <Button size="sm">Dashboard</Button>
                </Link>
              ) : (
                <Link href={login()}>
                  <Button variant="ghost" size="sm">Войти</Button>
                </Link>
              )}
            </div>
          </div>
        </header>

        {/* Hero Section */}
        <section className="relative pt-32 pb-20 overflow-hidden">
          {/* Background gradient */}
          <div className="absolute inset-0 -z-10">
            <div className="absolute top-0 left-1/2 -translate-x-1/2 w-[800px] h-[800px] bg-primary/5 rounded-full blur-3xl" />
            <div className="absolute top-20 right-0 w-[400px] h-[400px] bg-primary/3 rounded-full blur-3xl" />
            <div className="absolute bottom-0 left-0 w-[300px] h-[300px] bg-blue-400/10 rounded-full blur-3xl" />
          </div>

          <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div className="text-center max-w-4xl mx-auto">
              <div className="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-primary/10 text-primary text-sm font-medium mb-6">
                <Zap className="w-4 h-4" />
                API Backend для мобильного приложения
              </div>

              <h1 className="text-5xl sm:text-6xl lg:text-7xl font-bold tracking-tight mb-6">
                <span className="text-foreground">ckaccounting</span>
                <br />
                <span className="text-primary">API</span>
              </h1>

              <p className="text-xl text-muted-foreground max-w-2xl mx-auto mb-10 leading-relaxed">
                Мощный REST API для учёта магазина. Продажи, склад, долги, аналитика —
                всё что нужно для вашего мобильного приложения.
              </p>

              <div className="flex flex-col sm:flex-row items-center justify-center gap-4">
                <Link href="#api">
                  <Button size="lg">
                    Смотреть API
                  </Button>
                </Link>
              </div>
            </div>

            {/* Stats */}
            <div className="mt-20 grid grid-cols-3 gap-8 max-w-2xl mx-auto">
              {stats.map((stat, i) => (
                <div key={i} className="text-center">
                  <div className="text-3xl font-bold text-primary">{stat.value}</div>
                  <div className="text-sm text-muted-foreground mt-1">{stat.label}</div>
                </div>
              ))}
            </div>
          </div>
        </section>

        {/* Features Section */}
        <section id="features" className="py-24 bg-muted/30">
          <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div className="text-center mb-16">
              <h2 className="text-3xl sm:text-4xl font-bold mb-4">
                Все возможности для учёта магазина
              </h2>
              <p className="text-muted-foreground text-lg max-w-2xl mx-auto">
                Готовые эндпоинты для любых задач — от простого учёта до полноценной автоматизации
              </p>
            </div>

            <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
              {features.map((feature, i) => (
                <div
                  key={i}
                  className="group relative p-6 rounded-2xl bg-background border border-border hover:border-primary/50 transition-all duration-300 hover:shadow-lg hover:shadow-primary/5"
                >
                  <div className="w-12 h-12 rounded-xl bg-primary/10 flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
                    <feature.icon className="w-6 h-6 text-primary" />
                  </div>
                  <h3 className="font-semibold text-lg mb-2">{feature.title}</h3>
                  <p className="text-muted-foreground text-sm leading-relaxed">{feature.desc}</p>
                </div>
              ))}
            </div>
          </div>
        </section>

        {/* API Preview Section */}
        <section id="api" className="py-24">
          <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div className="grid lg:grid-cols-2 gap-12 items-center">
              <div>
                <h2 className="text-3xl sm:text-4xl font-bold mb-6">
                  Простой и понятный API
                </h2>
                <p className="text-muted-foreground text-lg mb-8 leading-relaxed">
                  RESTful API с чёткой структурой. JSON ответы, пагинация,
                  фильтрация — всё для удобной интеграции с мобильным приложением.
                </p>

                <div className="space-y-4">
                  {['products', 'sales', 'purchases', 'debts', 'expenses', 'reports'].map((endpoint) => (
                    <div key={endpoint} className="flex items-center gap-3">
                      <CheckCircle className="w-5 h-5 text-primary shrink-0" />
                      <code className="text-sm bg-muted px-3 py-1 rounded-md font-mono">
                        /api/v1/{endpoint}
                      </code>
                    </div>
                  ))}
                </div>
              </div>

              <div className="relative">
                <div className="absolute inset-0 bg-gradient-to-tr from-primary/20 to-transparent rounded-2xl blur-2xl" />
                <div className="relative bg-sidebar rounded-xl p-6 font-mono text-sm overflow-hidden">
                  <div className="flex gap-1.5 mb-4">
                    <div className="w-3 h-3 rounded-full bg-red-500/80" />
                    <div className="w-3 h-3 rounded-full bg-yellow-500/80" />
                    <div className="w-3 h-3 rounded-full bg-green-500/80" />
                  </div>
                  <pre className="text-sidebar-foreground/80 leading-relaxed">
{`{
  "data": [
    {
      "id": 1,
      "name": "iPhone 15 Pro",
      "stock": 25,
      "cost_price": 999.00,
      "sale_price": 1299.00,
      "is_low_stock": false
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 15,
    "total": 72
  }
}`}
                  </pre>
                </div>
              </div>
            </div>
          </div>
        </section>

        {/* Tech Stack Section */}
        <section className="py-24 bg-muted/30">
          <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div className="text-center mb-12">
              <h2 className="text-3xl font-bold mb-4">Технологии</h2>
              <p className="text-muted-foreground">Надёжный и современный стек</p>
            </div>

            <div className="flex flex-wrap justify-center gap-8 items-center">
              {['Laravel 12', 'PHP 8.4', 'PostgreSQL', 'Sanctum', 'Inertia', 'React 19'].map((tech) => (
                <div
                  key={tech}
                  className="px-6 py-3 rounded-full bg-background border border-border text-sm font-medium"
                >
                  {tech}
                </div>
              ))}
            </div>
          </div>
        </section>

        {/* Footer */}
        <footer className="border-t py-12">
          <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div className="flex flex-col md:flex-row items-center justify-between gap-4">
              <div className="flex items-center gap-2">
                <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-primary text-primary-foreground">
                  <span className="font-bold text-xs">CK</span>
                </div>
                <span className="font-semibold">ckaccounting</span>
              </div>

              <p className="text-sm text-muted-foreground">
                © 2026 ckaccounting. API для учёта магазина.
              </p>
            </div>
          </div>
        </footer>
      </div>
    </>
  )
}
