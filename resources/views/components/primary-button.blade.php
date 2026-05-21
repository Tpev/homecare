<button {{ $attributes->merge(['type' => 'submit', 'class' => 'hc-primary-button inline-flex w-full gap-2 border border-transparent px-4 py-3 text-sm font-semibold shadow-none focus:outline-none focus:ring-2 focus:ring-[#C96B55]/35 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60 sm:w-auto']) }}>
    {{ $slot }}
</button>
