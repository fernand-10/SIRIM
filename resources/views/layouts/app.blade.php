
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Panel') · SIRIM</title>

    <style>
        :root {
            --navy: #142b4a;
            --blue: #245b91;
            --blue-dark: #194875;
            --blue-light: #eaf2fb;
            --bg: #f4f6f9;
            --white: #ffffff;
            --text: #1e293b;
            --muted: #64748b;
            --line: #dbe3ed;
            --green: #15803d;
            --red: #b91c1c;
            --amber: #92400e;
        }

        * {
            box-sizing: border-box;
        }

        html {
            min-height: 100%;
        }

        body {
            margin: 0;
            font-family: Inter, "Segoe UI", Arial, sans-serif;
            background: var(--bg);
            color: var(--text);
            font-size: 14px;
            line-height: 1.5;
        }

        a {
            color: var(--blue);
            text-decoration: none;
        }

        a:hover {
            text-decoration: underline;
        }

        /* ENCABEZADO */

        .topbar {
            background: linear-gradient(120deg, var(--navy), var(--blue));
            color: var(--white);
            padding: 16px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            box-shadow: 0 3px 12px #142b4a20;
        }

        .brand {
            font-size: 22px;
            font-weight: 800;
            letter-spacing: 0.06em;
        }

        .subbrand {
            font-size: 12px;
            color: #d5e4f4;
            margin-top: 2px;
        }

        .topuser {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .topuser form {
            margin: 0;
        }

        /* NAVEGACIÓN */

        .nav {
            background: var(--white);
            border-bottom: 1px solid var(--line);
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 10px 22px;
            flex-wrap: wrap;
        }

        .nav a {
            color: #31445e;
            padding: 9px 12px;
            border-radius: 7px;
            font-weight: 600;
            transition: background 0.2s, color 0.2s;
        }

        .nav a:hover {
            background: var(--blue-light);
            color: var(--blue);
            text-decoration: none;
        }

        /* CONTENIDO GENERAL */

        .container {
            max-width: 1240px;
            margin: 26px auto;
            padding: 0 18px;
            min-height: 55vh;
        }

        .pagehead {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 14px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .pagehead h1 {
            font-size: 26px;
            line-height: 1.25;
            margin: 0 0 6px;
            color: var(--navy);
        }

        .muted {
            color: var(--muted);
        }

        .sectiontitle {
            font-size: 17px;
            color: var(--navy);
            font-weight: 800;
            margin: 0 0 14px;
        }

        /* TARJETAS */

        .card {
            background: var(--white);
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 17px;
            box-shadow: 0 3px 12px #142b4a08;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 15px;
        }

        .grid-3 {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 15px;
        }

        .grid-4 {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 15px;
        }

        .stat {
            background: var(--white);
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 19px;
            box-shadow: 0 3px 12px #142b4a08;
        }

        .stat .num {
            font-size: 30px;
            font-weight: 800;
            color: var(--navy);
        }

        .stat .label {
            color: var(--muted);
            font-weight: 600;
        }

        .kpihelp {
            font-size: 12px;
            color: var(--muted);
            margin-top: 4px;
        }

        /* BOTONES */

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            border: 0;
            border-radius: 7px;
            background: var(--blue);
            color: var(--white);
            padding: 10px 14px;
            font-weight: 700;
            cursor: pointer;
            font-size: 13px;
            font-family: inherit;
            transition: background 0.2s;
        }

        .btn:hover {
            color: var(--white);
            background: var(--navy);
            text-decoration: none;
        }

        .btn.secondary {
            background: #e8eef5;
            color: #263c58;
        }

        .btn.secondary:hover {
            background: #d6e2ef;
            color: var(--navy);
        }

        .btn.danger {
            background: #fee2e2;
            color: #991b1b;
        }

        .btn.danger:hover {
            background: #fecaca;
        }

        .btn.small {
            padding: 6px 9px;
            font-size: 12px;
        }

        .actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            align-items: center;
        }

        /* TABLAS */

        .tablewrap {
            overflow-x: auto;
            width: 100%;
        }

        table {
            border-collapse: collapse;
            width: 100%;
            font-size: 13px;
        }

        th,
        td {
            text-align: left;
            vertical-align: top;
            padding: 11px 10px;
            border-bottom: 1px solid #e7edf4;
        }

        th {
            color: #52657d;
            background: #f1f6fb;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        tbody tr:hover {
            background: #f8fbff;
        }

        tr:last-child td {
            border-bottom: 0;
        }

        /* FORMULARIOS */

        .formgrid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 15px;
        }

        .field {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .field.full {
            grid-column: 1 / -1;
        }

        label {
            font-weight: 700;
            font-size: 13px;
            color: #334155;
        }

        input,
        select,
        textarea {
            width: 100%;
            border: 1px solid #cbd5e1;
            background: var(--white);
            border-radius: 7px;
            padding: 10px 11px;
            font: inherit;
            color: var(--text);
        }

        textarea {
            min-height: 100px;
            resize: vertical;
        }

        input:focus,
        select:focus,
        textarea:focus {
            outline: 2px solid #bfdbfe;
            border-color: #60a5fa;
        }

        .hint {
            font-size: 12px;
            color: var(--muted);
        }

        /* MENSAJES */

        .alert {
            padding: 13px 16px;
            border-radius: 8px;
            margin: 0 0 16px;
            border: 1px solid transparent;
        }

        .alert.success {
            background: #dcfce7;
            color: #166534;
            border-color: #bbf7d0;
        }

        .alert.error {
            background: #fee2e2;
            color: #991b1b;
            border-color: #fecaca;
        }

        .errors {
            color: var(--red);
            font-size: 12px;
        }

        /* ESTADOS */

        .badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 9px;
            border-radius: 20px;
            background: #e2e8f0;
            color: #334155;
            font-size: 11px;
            font-weight: 800;
            white-space: nowrap;
        }

        .badge.green {
            background: #dcfce7;
            color: #166534;
        }

        .badge.blue {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .badge.amber {
            background: #fef3c7;
            color: #92400e;
        }

        .badge.red {
            background: #fee2e2;
            color: #991b1b;
        }

        /* SEGUIMIENTO */

        .timeline {
            border-left: 2px solid #dbeafe;
            margin: 8px 0 8px 8px;
            padding-left: 18px;
        }

        .event {
            position: relative;
            padding: 0 0 17px;
        }

        .event::before {
            content: "";
            position: absolute;
            left: -24px;
            top: 5px;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #3b82f6;
            border: 3px solid #dbeafe;
        }

        .split {
            display: flex;
            gap: 16px;
            align-items: flex-start;
        }

        .split > * {
            flex: 1;
            min-width: 0;
        }

        .divider {
            height: 1px;
            background: var(--line);
            margin: 18px 0;
        }

        .pagination {
            margin-top: 16px;
            overflow-x: auto;
        }

        .pagination nav {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .empty {
            padding: 24px;
            text-align: center;
            color: var(--muted);
        }

        /* PIE DE PÁGINA */

        .footer {
            color: var(--muted);
            font-size: 11px;
            padding: 22px 0 30px;
            text-align: center;
        }

        /* INICIO DE SESIÓN */

        .loginpage {
            min-height: 100vh;
            display: grid;
            place-items: center;
            background: linear-gradient(140deg, #102744, #2f679e);
            padding: 20px;
        }

        .loginbox {
            background: var(--white);
            padding: 30px;
            border-radius: 15px;
            width: min(440px, 100%);
            box-shadow: 0 22px 70px #0003;
        }

        .loginbox h1 {
            margin: 0;
            color: var(--navy);
            font-size: 29px;
        }

        .loginbox .sub {
            margin: 5px 0 22px;
            color: var(--muted);
        }

        .searchrow {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 15px;
        }

        .searchrow input,
        .searchrow select {
            max-width: 320px;
        }

        /* DISEÑO RESPONSIVO */

        @media (max-width: 900px) {
            .grid-4,
            .grid-3 {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .split {
                display: block;
            }

            .split > * {
                margin-bottom: 12px;
            }
        }

        @media (max-width: 620px) {
            .topbar {
                align-items: flex-start;
                flex-direction: column;
                padding: 15px;
            }

            .container {
                padding: 0 11px;
                margin: 17px auto;
            }

            .grid,
            .grid-3,
            .grid-4,
            .formgrid {
                grid-template-columns: 1fr;
            }

            .field.full {
                grid-column: auto;
            }

            .pagehead h1 {
                font-size: 22px;
            }

            .nav {
                padding: 8px 10px;
            }

            .nav a {
                padding: 7px 9px;
                font-size: 12px;
            }

            .card,
            .stat {
                padding: 14px;
            }

            .loginbox {
                padding: 22px;
            }
        }
    </style>
</head>

<body>

    <!-- ENCABEZADO PRINCIPAL -->
    <header class="topbar">
        <div>
            <div class="brand">SIRIM</div>
            <div class="subbrand">
                Sistema de Referencias e Interconsultas Médicas
            </div>
        </div>

        @auth
            <div class="topuser">
                <span>
                    {{ auth()->user()->name }}
                    ·
                    {{ auth()->user()->roleLabel() }}
                </span>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf

                    <button class="btn secondary small" type="submit">
                        Cerrar sesión
                    </button>
                </form>
            </div>
        @endauth
    </header>

    <!-- MENÚ DE NAVEGACIÓN -->
    @auth
        <nav class="nav">
            <a href="{{ route('dashboard') }}">
                Panel
            </a>

            <a href="{{ route('pacientes.index') }}">
                Pacientes
            </a>

            <a href="{{ route('referencias.index') }}">
                Referencias
            </a>

            <a href="{{ route('interconsultas.index') }}">
                Interconsultas
            </a>

            <a href="{{ route('citas.index') }}">
                Agenda interna
            </a>

            @if(in_array(auth()->user()->role, ['admin', 'doctor']))
                <a href="{{ route('atenciones.index') }}">
                    Atenciones
                </a>
            @endif

            @if(in_array(auth()->user()->role, ['admin', 'coordinator', 'admission']))
                <a href="{{ route('reportes.index') }}">
                    Reportes
                </a>
            @endif

            @if(in_array(auth()->user()->role, ['admin', 'coordinator']))
                <a href="{{ route('horarios.index') }}">Horarios médicos</a>
            @endif

            @if(auth()->user()->role === 'admin')
                <a href="{{ route('admin.index') }}">Administración</a>
            @endif
        </nav>
    @endauth

    <!-- CONTENIDO DE CADA PANTALLA -->
    <main class="container">

        @if(session('success'))
            <div class="alert success">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="alert error">
                {{ session('error') }}
            </div>
        @endif

        @if($errors->any())
            <div class="alert error">
                <strong>Revisa los siguientes campos:</strong>

                <ul style="margin: 6px 0 0; padding-left: 20px;">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')

        <footer class="footer">
            SIRIM · Prototipo académico local.
            No usar con datos reales sin autorización y evaluación de seguridad.
        </footer>

    </main>

</body>
</html>
