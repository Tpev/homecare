{!! '<?xml version="1.0" encoding="UTF-8"?>' !!}
<feed xmlns="http://www.w3.org/2005/Atom">
    <title>LoLo Care Resources</title>
    <subtitle>Reviewed local care guidance for Raleigh and the Triangle.</subtitle>
    <link href="{{ route('blog.feed') }}" rel="self" type="application/atom+xml" />
    <link href="{{ route('blog.index') }}" rel="alternate" type="text/html" />
    <id>{{ route('blog.index') }}</id>
    <updated>{{ optional($posts->first()['modified_at'] ?? null)?->toAtomString() ?? $feedUpdatedAt->toAtomString() }}</updated>
@foreach($posts as $post)
    <entry>
        <title>{{ $post['title'] }}</title>
        <link href="{{ $post['url'] }}" />
        <id>{{ $post['url'] }}</id>
        <published>{{ $post['published_at']?->toAtomString() }}</published>
        <updated>{{ $post['modified_at']?->toAtomString() }}</updated>
        @if($post['author'])<author><name>{{ $post['author']->name }}</name><uri>{{ route('blog.author',$post['author']) }}</uri></author>@endif
        <summary>{{ $post['excerpt'] }}</summary>
@foreach($post['categories'] as $category)
        <category term="{{ $category->slug }}" label="{{ $category->name }}" />
@endforeach
    </entry>
@endforeach
</feed>
