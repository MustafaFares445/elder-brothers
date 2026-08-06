<style>
    [x-cloak] {
        display: none !important;
    }

    input[type="file"][accept*="video/mp4"] {
        position: absolute !important;
        width: 1px !important;
        height: 1px !important;
        padding: 0 !important;
        margin: -1px !important;
        overflow: hidden !important;
        clip: rect(0, 0, 0, 0) !important;
        white-space: nowrap !important;
        border: 0 !important;
    }

    input[type="file"][accept*="video/mp4"] + div {
        border: 1px dashed #d1d5db;
        border-radius: 0.75rem;
        background: #f9fafb;
        padding: 1.25rem;
        transition: border-color 150ms ease, background-color 150ms ease, box-shadow 150ms ease;
    }

    input[type="file"][accept*="video/mp4"] + div:focus-within,
    input[type="file"][accept*="video/mp4"] + div:hover {
        border-color: #ddb867;
        background: #fffdf8;
        box-shadow: 0 0 0 3px rgb(221 184 103 / 15%);
    }

    input[type="file"][accept*="video/mp4"] + div > div {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
    }

    input[type="file"][accept*="video/mp4"] + div > div > div {
        min-width: 0;
        flex: 1;
    }

    input[type="file"][accept*="video/mp4"] + div p {
        margin: 0;
    }

    input[type="file"][accept*="video/mp4"] + div p:first-child {
        overflow: hidden;
        color: #111827;
        font-size: 0.875rem;
        font-weight: 600;
        line-height: 1.5;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    input[type="file"][accept*="video/mp4"] + div p + p {
        margin-top: 0.35rem;
        color: #6b7280;
        font-size: 0.75rem;
        line-height: 1.6;
    }

    input[type="file"][accept*="video/mp4"] + div button {
        display: inline-flex;
        flex-shrink: 0;
        align-items: center;
        justify-content: center;
        min-height: 2.5rem;
        border: 0;
        border-radius: 0.625rem;
        background: #ddb867;
        padding: 0.625rem 1rem;
        color: #4d3922;
        font: inherit;
        font-size: 0.875rem;
        font-weight: 700;
        cursor: pointer;
        box-shadow: 0 1px 2px rgb(0 0 0 / 8%);
        transition: filter 150ms ease, opacity 150ms ease, transform 150ms ease;
    }

    input[type="file"][accept*="video/mp4"] + div button:hover:not(:disabled) {
        filter: brightness(1.04);
        transform: translateY(-1px);
    }

    input[type="file"][accept*="video/mp4"] + div button:disabled {
        cursor: not-allowed;
        opacity: 0.55;
    }

    input[type="file"][accept*="video/mp4"] + div + div {
        margin-top: 0.75rem;
        border: 1px solid #e5e7eb;
        border-radius: 0.75rem;
        background: #fff;
        padding: 1rem;
        box-shadow: 0 1px 2px rgb(0 0 0 / 5%);
    }

    input[type="file"][accept*="video/mp4"] + div + div > div:first-child {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
    }

    input[type="file"][accept*="video/mp4"] + div + div > div:first-child > div {
        min-width: 0;
    }

    input[type="file"][accept*="video/mp4"] + div + div > div:first-child p {
        margin: 0;
    }

    input[type="file"][accept*="video/mp4"] + div + div > div:first-child p:first-child {
        color: #111827;
        font-size: 0.875rem;
        font-weight: 700;
    }

    input[type="file"][accept*="video/mp4"] + div + div > div:first-child p + p {
        margin-top: 0.25rem;
        overflow: hidden;
        color: #6b7280;
        font-size: 0.75rem;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    input[type="file"][accept*="video/mp4"] + div + div > div:first-child > span {
        flex-shrink: 0;
        color: #9a741e;
        font-size: 0.875rem;
        font-weight: 700;
        font-variant-numeric: tabular-nums;
    }

    input[type="file"][accept*="video/mp4"] + div + div > div:nth-of-type(2) {
        height: 0.5rem;
        margin-top: 0.75rem;
        overflow: hidden;
        border-radius: 9999px;
        background: #e5e7eb;
    }

    input[type="file"][accept*="video/mp4"] + div + div > div:nth-of-type(2) > div {
        height: 100%;
        border-radius: inherit;
        background: #ddb867;
        transition: width 300ms ease;
    }

    input[type="file"][accept*="video/mp4"] + div + div .bg-primary-600 {
        background: #ddb867 !important;
    }

    input[type="file"][accept*="video/mp4"] + div + div .bg-success-600 {
        background: #16a34a !important;
    }

    input[type="file"][accept*="video/mp4"] + div + div .bg-danger-600 {
        background: #dc2626 !important;
    }

    input[type="file"][accept*="video/mp4"] + div + div > div:nth-of-type(3) {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 0.5rem;
        margin-top: 0.75rem;
        color: #4b5563;
        font-size: 0.75rem;
    }

    input[type="file"][accept*="video/mp4"] + div + div > p {
        margin: 0.75rem 0 0;
        border-radius: 0.5rem;
        background: #fef2f2;
        padding: 0.625rem 0.75rem;
        color: #b91c1c;
        font-size: 0.875rem;
        line-height: 1.5;
    }

    input[type="file"][accept*="video/mp4"] + div + div > div:last-child {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        margin-top: 1rem;
    }

    input[type="file"][accept*="video/mp4"] + div + div > div:last-child button {
        min-height: 2.25rem;
        border: 1px solid #d1d5db;
        border-radius: 0.5rem;
        background: #fff;
        padding: 0.45rem 0.8rem;
        color: #374151;
        font: inherit;
        font-size: 0.875rem;
        font-weight: 600;
        cursor: pointer;
        transition: background-color 150ms ease, border-color 150ms ease;
    }

    input[type="file"][accept*="video/mp4"] + div + div > div:last-child button:hover {
        border-color: #ddb867;
        background: #fffdf8;
    }

    input[type="file"][accept*="video/mp4"] + div + div > div:last-child button.bg-primary-600 {
        border-color: #ddb867;
        background: #ddb867;
        color: #4d3922;
    }

    input[type="file"][accept*="video/mp4"] + div + div > div:last-child button.text-danger-600 {
        border-color: transparent;
        background: transparent;
        color: #dc2626;
    }

    input[type="file"][accept*="video/mp4"] + div + div > div:last-child button.text-danger-600:hover {
        background: #fef2f2;
    }

    html.dark input[type="file"][accept*="video/mp4"] + div {
        border-color: #4b5563;
        background: rgb(17 24 39 / 70%);
    }

    html.dark input[type="file"][accept*="video/mp4"] + div:focus-within,
    html.dark input[type="file"][accept*="video/mp4"] + div:hover {
        border-color: #ddb867;
        background: rgb(31 41 55 / 85%);
    }

    html.dark input[type="file"][accept*="video/mp4"] + div p:first-child,
    html.dark input[type="file"][accept*="video/mp4"] + div + div > div:first-child p:first-child {
        color: #f9fafb;
    }

    html.dark input[type="file"][accept*="video/mp4"] + div p + p,
    html.dark input[type="file"][accept*="video/mp4"] + div + div > div:first-child p + p {
        color: #9ca3af;
    }

    html.dark input[type="file"][accept*="video/mp4"] + div + div {
        border-color: #374151;
        background: #111827;
    }

    html.dark input[type="file"][accept*="video/mp4"] + div + div > div:nth-of-type(2) {
        background: #374151;
    }

    html.dark input[type="file"][accept*="video/mp4"] + div + div > div:nth-of-type(3) {
        color: #d1d5db;
    }

    html.dark input[type="file"][accept*="video/mp4"] + div + div > div:last-child button {
        border-color: #4b5563;
        background: #1f2937;
        color: #e5e7eb;
    }

    @media (max-width: 640px) {
        input[type="file"][accept*="video/mp4"] + div > div {
            align-items: stretch;
            flex-direction: column;
        }

        input[type="file"][accept*="video/mp4"] + div button {
            width: 100%;
        }

        input[type="file"][accept*="video/mp4"] + div + div > div:nth-of-type(3) {
            grid-template-columns: 1fr;
        }
    }
</style>
