<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex w-full items-center justify-center gap-2 rounded-[1rem] border border-transparent bg-[#B84D57] px-4 py-3 text-sm font-semibold text-white transition hover:bg-[#A6434C] focus:outline-none focus:ring-2 focus:ring-[#B84D57]/35 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60 sm:w-auto']) }}>
    {{ $slot }}
</button>
