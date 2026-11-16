<div class="reapit-property-detail container py-5">
    <div class="row">
        <div class="col-12">
    <p><a href="$Link.ATT">All properties</a></p>
    <% with $Property %>
        <article class="mb-5">
            <header class="mb-4">
                <h1>$Title.XML</h1>
                <% if $Address %><p>$Address.XML<% if $Postcode %>, $Postcode.XML<% end_if %></p><% end_if %>
                <% if $Rent %><p class="reapit-property-detail__rent">&pound;$Rent.Nice <% if $RentFrequency %>/ $RentFrequency.XML<% end_if %></p><% end_if %>
            </header>

            <% if $Images.Count %>
                <div class="row mb-4" data-reapit-gallery>
                    <% loop $Images %>
                        <% if $Image %>
                            <div class="col-md-6 col-lg-4 mb-4">
                                <figure class="mb-0">
                                    <% with $Image %>
                                        <% with $ScaleMaxWidth(1600) %>
                                            <a href="$URL.ATT" data-pswp-width="$Width" data-pswp-height="$Height" aria-label="View larger property photograph">
                                                <% with $Up.ScaleMaxWidth(550) %>
                                                    <img class="img-fluid" src="$URL.ATT" width="$Width" height="$Height" alt="$Top.Property.Title.ATT" loading="lazy" decoding="async">
                                                <% end_with %>
                                            </a>
                                        <% end_with %>
                                    <% end_with %>
                                    <% if $Caption %><figcaption>$Caption.XML</figcaption><% end_if %>
                                </figure>
                            </div>
                        <% end_if %>
                    <% end_loop %>
                </div>
            <% end_if %>

            <dl class="reapit-property-detail__facts mb-4">
                <% if $Bedrooms %><div><dt>Bedrooms</dt><dd>$Bedrooms</dd></div><% end_if %>
                <% if $Bathrooms %><div><dt>Bathrooms</dt><dd>$Bathrooms</dd></div><% end_if %>
                <% if $LettingStatus %><div><dt>Status</dt><dd>$LettingStatus.XML</dd></div><% end_if %>
            </dl>

            <% if $Summary %>
                <p class="reapit-property-detail__summary">$Summary.XML</p>
            <% end_if %>

            <% if $LongDescription %>
                <div class="reapit-property-detail__description">$LongDescription</div>
            <% else_if $Description %>
                <div class="reapit-property-detail__description">$Description</div>
            <% end_if %>
        </article>
    <% end_with %>
        </div>
    </div>
</div>
