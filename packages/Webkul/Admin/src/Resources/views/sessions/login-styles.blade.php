<style>
    .sign-in {
        --login-surface: #f8fafd;
        --login-input: #fff;
        --login-border: #ccd7e7;
        --login-text: #101e36;
        --login-muted: #516480;
        --login-accent: #2456ab;
        --login-focus: #2d66cc;
        display: grid;
        grid-template-columns: minmax(0, 1.18fr) minmax(0, 1fr);
        min-height: 100vh;
        min-height: 100svh;
        color: var(--login-text);
        font-family: Inter, system-ui, sans-serif;
    }

    .sign-in[data-theme="dark"] {
        --login-surface: #101a2b;
        --login-input: #172438;
        --login-border: #3c516e;
        --login-text: #f2f6ff;
        --login-muted: #b4c4db;
        --login-accent: #a1c6ff;
        --login-focus: #96bdff;
    }

    .sign-in-hero {
        position: relative;
        isolation: isolate;
        display: grid;
        grid-template-rows: auto 1fr auto;
        gap: 1.5rem;
        overflow: hidden;
        padding: clamp(2.25rem, 5vw, 5.25rem);
        color: #fff;
        background:
            linear-gradient(135deg, transparent 56%, #3563b124 56%, #3563b124 70%, transparent 70%),
            linear-gradient(135deg, transparent 66%, #5788e428 66%, #5788e428 81%, transparent 81%),
            radial-gradient(ellipse at 56% 44%, #143876 0%, #091c3c 48%, #050f23 83%);
    }

    .sign-in-hero::before {
        position: absolute;
        z-index: -1;
        inset: 0;
        background-image:
            linear-gradient(#a7c3f711 1px, transparent 1px),
            linear-gradient(90deg, #a7c3f711 1px, transparent 1px);
        background-size: 48px 48px;
        mask-image: radial-gradient(ellipse at 51% 45%, #000, transparent 75%);
        pointer-events: none;
        content: '';
    }

    .sign-in-identity {
        position: relative;
        z-index: 1;
        display: flex;
        align-items: center;
        gap: .9rem;
        font-size: clamp(1.15rem, 2vw, 1.65rem);
        font-weight: 800;
        letter-spacing: .14em;
    }

    .sign-in-identity svg {
        width: 2.75rem;
        height: auto;
        flex: none;
    }

    .sign-in-identity span {
        padding-inline-start: .85rem;
        border-inline-start: 1px solid #7c9dd5a0;
    }

    .sign-in-identity img {
        width: auto;
        max-width: 100%;
        height: 2.75rem;
        object-fit: contain;
    }

    .sign-in-emblem {
        position: relative;
        display: grid;
        place-items: center;
        width: min(100%, 26rem, 47svh);
        aspect-ratio: 1;
        align-self: center;
        justify-self: center;
    }

    .sign-in-orbit {
        position: absolute;
        inset: 0;
        border: 1px solid #91b5fe38;
        border-radius: 50%;
        box-shadow: 0 0 72px #4d79d21a, inset 0 0 42px #4d79d214;
    }

    .sign-in-orbit::before,
    .sign-in-orbit::after {
        position: absolute;
        border-radius: 50%;
        content: '';
    }

    .sign-in-orbit::before {
        inset: 7%;
        border: 1px dashed #99b9fa64;
        border-inline-start-color: #a9c9ffba;
        animation: sign-in-rotation 32s linear infinite reverse;
    }

    .sign-in-orbit::after {
        inset: -1px;
        border: 1px solid transparent;
        border-top-color: #b8d5fbc9;
        border-right-color: #6ea8f66b;
        animation: sign-in-rotation 19s linear infinite;
    }

    .sign-in-orbit-tracer {
        position: absolute;
        inset: 0;
        animation: sign-in-rotation 19s linear infinite;
    }

    .sign-in-orbit-tracer::after {
        position: absolute;
        top: 50%;
        right: 0;
        width: .45rem;
        height: .45rem;
        border-radius: 50%;
        background: #dceaff;
        box-shadow: 0 0 12px 3px #7fabfa99;
        translate: 50% -50%;
        content: '';
    }

    .sign-in-emblem > svg {
        position: relative;
        width: 59%;
        height: auto;
        color: #f3f7ff;
        filter: drop-shadow(0 12px 26px #0006);
        animation: sign-in-float 7s ease-in-out infinite;
    }

    .sign-in-story {
        position: relative;
        padding-inline-start: 1rem;
        border-inline-start: 2px solid #769ce8;
    }

    .sign-in-story p {
        margin: 0;
        font-size: clamp(1.3rem, 2.65vw, 2.35rem);
        font-weight: 690;
        line-height: 1.12;
        letter-spacing: -.04em;
        text-wrap: balance;
    }

    .sign-in-story span {
        display: block;
        margin-top: .55rem;
        color: #c2d4f5;
        font-size: .9rem;
        line-height: 1.5;
    }

    .sign-in-main {
        display: grid;
        place-items: center;
        min-width: 0;
        padding: 3.5rem clamp(1.5rem, 4vw, 4.25rem);
        border-inline-start: 1px solid #d1def34d;
        background: var(--login-surface);
    }

    .sign-in-content {
        width: 100%;
        max-width: 26.5rem;
    }

    .sign-in-heading {
        margin-bottom: 2.25rem;
    }

    .sign-in-portal {
        display: inline-flex;
        align-items: center;
        gap: .45rem;
        margin: 0 0 .95rem;
        color: var(--login-accent);
        font-size: .82rem;
        font-weight: 650;
    }

    .sign-in-portal svg,
    .sign-in-input-icon,
    .sign-in-visibility svg,
    .sign-in-submit svg {
        width: 1.25rem;
        height: 1.25rem;
        flex: none;
    }

    .sign-in-heading h1 {
        margin: 0;
        color: var(--login-text);
        font-size: clamp(2.1rem, 3.7vw, 3.2rem);
        font-weight: 750;
        letter-spacing: -.055em;
        line-height: 1.08;
        text-wrap: balance;
    }

    .sign-in-form {
        display: grid;
        gap: 1.25rem;
    }

    .sign-in-form .sign-in-field {
        margin: 0;
    }

    .sign-in-form label {
        margin-bottom: .55rem;
        color: var(--login-text);
        font-size: .92rem;
        font-weight: 630;
    }

    .sign-in-password-label {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        flex-wrap: wrap;
        column-gap: .85rem;
    }

    .sign-in-password-label a {
        margin-bottom: .5rem;
        color: var(--login-accent);
        font-size: .83rem;
        font-weight: 630;
        text-underline-offset: .18em;
    }

    .sign-in-password-label a:hover {
        text-decoration: underline;
    }

    .sign-in-control {
        position: relative;
    }

    .sign-in-input-icon {
        position: absolute;
        z-index: 1;
        inset-block-start: 50%;
        inset-inline-start: 1rem;
        color: var(--login-muted);
        translate: 0 -50%;
        pointer-events: none;
    }

    .sign-in-control .sign-in-input {
        min-height: 3.45rem;
        padding-inline-start: 2.85rem;
        border: 1px solid var(--login-border);
        border-radius: .75rem;
        background: var(--login-input);
        color: var(--login-text);
        font-size: 1rem;
        line-height: 1.4;
    }

    .sign-in-control .sign-in-input::placeholder {
        color: var(--login-muted);
        opacity: 1;
    }

    .sign-in-control .sign-in-password {
        padding-inline-end: 3.25rem;
    }

    .sign-in-control .sign-in-input:focus {
        border-color: var(--login-focus);
        outline: 2px solid var(--login-focus);
        outline-offset: 1px;
        box-shadow: none;
    }

    .sign-in-visibility {
        position: absolute;
        inset-block-start: 50%;
        inset-inline-end: .4rem;
        display: grid;
        place-items: center;
        width: 2.8rem;
        height: 2.8rem;
        border: 0;
        border-radius: .55rem;
        background: transparent;
        color: var(--login-muted);
        cursor: pointer;
        translate: 0 -50%;
    }

    .sign-in-visibility:hover,
    .sign-in-visibility[aria-pressed="true"] {
        background: var(--login-surface);
        color: var(--login-text);
    }

    .sign-in-visibility[aria-pressed="false"] .sign-in-visibility-slash {
        display: none;
    }

    .sign-in-submit {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        min-height: 3.45rem;
        margin-top: .35rem;
        padding: .75rem 1.05rem;
        border: 1px solid #1a468b;
        border-radius: .75rem;
        background: linear-gradient(110deg, #113369, #204e97);
        box-shadow: 0 11px 21px -13px #163f8aca;
        color: #fff;
        font-size: .97rem;
        font-weight: 680;
        cursor: pointer;
    }

    .sign-in-submit:hover {
        background: linear-gradient(110deg, #174785, #285caa);
    }

    [dir="rtl"] .sign-in-submit svg {
        scale: -1 1;
    }

    .sign-in button:focus-visible,
    .sign-in a:focus-visible {
        outline: 2px solid var(--login-focus);
        outline-offset: 3px;
    }

    @keyframes sign-in-rotation {
        to {
            transform: rotate(1turn);
        }
    }

    @keyframes sign-in-float {
        50% {
            transform: translateY(-6px);
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .sign-in-orbit::before,
        .sign-in-orbit::after,
        .sign-in-orbit-tracer,
        .sign-in-emblem > svg {
            animation: none;
        }
    }

    @media (max-width: 820px) {
        .sign-in {
            grid-template-columns: minmax(0, 1fr);
            grid-template-rows: auto 1fr;
        }

        .sign-in-hero {
            display: flex;
            align-items: center;
            min-height: 8rem;
            padding: 1.5rem clamp(1.25rem, 6vw, 3rem);
        }

        .sign-in-identity img {
            max-width: min(60vw, 15rem);
        }

        .sign-in-emblem {
            position: absolute;
            inset-block-start: -2.35rem;
            inset-inline-end: -1.75rem;
            width: 13rem;
            opacity: .36;
        }

        .sign-in-story {
            display: none;
        }

        .sign-in-main {
            align-items: start;
            padding: clamp(2rem, 7vh, 4rem) clamp(1.25rem, 6vw, 3rem) 2.5rem;
            border-inline-start: 0;
        }

        .sign-in-heading {
            margin-bottom: 1.8rem;
        }
    }

    @media (max-width: 360px) {
        .sign-in-identity {
            font-size: 1.1rem;
        }

        .sign-in-hero {
            min-height: 6.75rem;
        }
    }
</style>
