<button {{ $attributes->merge(['type' => 'button', 'class' => 'hc-secondary-button inline-flex w-full gap-2 px-4 py-3 text-sm font-semibold shadow-none focus:outline-none focus:ring-2 focus:ring-[#C96B55]/25 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60 sm:w-auto']) }}>
    {{ $slot }}
</button>
