<div class="reapit-property-detail">
    <p><a href="$Link.ATT">All properties</a></p>
    <% with $Property %>
        <article>
            <header>
                <h1>$Title.XML</h1>
                <% if $Address %><p>$Address.XML<% if $Postcode %>, $Postcode.XML<% end_if %></p><% end_if %>
                <% if $Rent %><p class="reapit-property-detail__rent">&pound;$Rent.Nice <% if $RentFrequency %>/ $RentFrequency.XML<% end_if %></p><% end_if %>
            </header>

            <% if $Images.Count %>
                <div class="reapit-property-detail__gallery">
                    <% loop $Images %>
                        <% if $Image %>
                            <figure>
                                <img src="$Image.ScaleMaxWidth(1200).URL.ATT" alt="$Up.Title.ATT" loading="lazy">
                                <% if $Caption %><figcaption>$Caption.XML</figcaption><% end_if %>
                            </figure>
                        <% end_if %>
                    <% end_loop %>
                </div>
            <% end_if %>

            <dl class="reapit-property-detail__facts">
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
