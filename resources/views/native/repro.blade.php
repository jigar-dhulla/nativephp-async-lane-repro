<native:column class="w-full h-full items-center justify-center gap-4 p-6">
    <native:text class="text-xl font-extrabold text-zinc-900 text-center">Async lane repro</native:text>
    <native:text class="text-base text-zinc-900 text-center">First dispatch (in mount):</native:text>
    <native:text class="text-base text-zinc-900 text-center">{{ $firstLine }}</native:text>
    <native:button @tap="dispatchAgain" class="bg-teal-700 rounded-lg px-6 py-3" color="#FFFFFF">Dispatch again</native:button>
    <native:text class="text-base text-zinc-900 text-center">{{ $againLine }}</native:text>
</native:column>
