<div class="reapit-property-detail container py-5">
    <nav class="reapit-property-detail__breadcrumb mb-4" aria-label="Breadcrumb">
        <a href="$Link.ATT">All properties</a>
        <span aria-hidden="true">/</span>
        <% with $Property %><span aria-current="page">$Title.XML</span><% end_with %>
    </nav>

    <% with $Property %>
        <header class="reapit-property-detail__header mb-4">
            <h1>$Title.XML</h1>
            <% if $Address %>
                <p class="reapit-property-detail__address">$Address.XML<% if $Postcode %>, $Postcode.XML<% end_if %></p>
            <% end_if %>
        </header>
    <% end_with %>

    <div class="row">
        <main class="col-lg-8">
            <% with $Property %>
                <% if $Images.Count %>
                    <div class="reapit-property-detail__gallery mb-5" data-reapit-gallery aria-label="Property photographs">
                        <% loop $Images %>
                            <% if $Image %>
                                <div class="reapit-property-detail__gallery-item">
                                    <figure>
                                        <% with $Image %>
                                            <% with $ScaleMaxWidth(1600) %>
                                                <a href="$URL.ATT" data-pswp-width="$Width" data-pswp-height="$Height" aria-label="View larger property photograph">
                                                    <% with $Up.ScaleMaxWidth(750) %>
                                                        <img src="$URL.ATT" width="$Width" height="$Height" alt="$Top.Property.Title.ATT" loading="lazy" decoding="async">
                                                    <% end_with %>
                                                    <span class="reapit-property-detail__gallery-more" aria-hidden="true">View all photos</span>
                                                </a>
                                            <% end_with %>
                                        <% end_with %>
                                    </figure>
                                </div>
                            <% end_if %>
                        <% end_loop %>
                    </div>
                <% end_if %>

                <% if $Summary || $LongDescription || $Description %>
                    <section class="reapit-property-detail__section mb-5" aria-labelledby="reapit-property-description">
                        <h2 id="reapit-property-description">Property description</h2>
                        <% if $Summary %><p class="reapit-property-detail__summary">$Summary.XML</p><% end_if %>
                        <% if $LongDescription %>
                            <div class="reapit-property-detail__description">$LongDescription</div>
                        <% else_if $Description %>
                            <div class="reapit-property-detail__description">$Description</div>
                        <% end_if %>
                    </section>
                <% end_if %>

                <section class="reapit-property-detail__section mb-5" aria-labelledby="reapit-property-information">
                    <h2 id="reapit-property-information">Key information</h2>
                    <dl class="reapit-property-detail__information">
                        <% if $Rent %><div><dt>Rent</dt><dd>&pound;$Rent.Nice <% if $RentFrequency %>/ $RentFrequency.XML<% end_if %></dd></div><% end_if %>
                        <% if $Bedrooms %><div><dt>Bedrooms</dt><dd>$Bedrooms</dd></div><% end_if %>
                        <% if $Bathrooms %><div><dt>Bathrooms</dt><dd>$Bathrooms</dd></div><% end_if %>
                        <% if $LettingStatus %><div><dt>Status</dt><dd>$LettingStatusLabel.XML</dd></div><% end_if %>
                        <% if $Postcode %><div><dt>Postcode</dt><dd>$Postcode.XML</dd></div><% end_if %>
                        <div><dt>Reference</dt><dd>$ReapitID.XML</dd></div>
                    </dl>
                </section>
            <% end_with %>
        </main>

        <aside class="col-lg-4 reapit-property-detail__sidebar" aria-label="Property enquiry and location">
            <div class="reapit-property-detail__panel mb-4">
                <% with $Property %>
                    <% if $Rent %>
                        <p class="reapit-property-detail__price">&pound;$Rent.Nice</p>
                        <% if $RentFrequency %><p class="reapit-property-detail__price-period">$RentFrequency.XML</p><% end_if %>
                    <% end_if %>
                    <% if $LettingStatus %><p class="reapit-property-detail__status">$LettingStatusLabel.XML</p><% end_if %>

                    <% if $Bedrooms || $Bathrooms %>
                        <dl class="reapit-property-detail__highlights">
                            <% if $Bedrooms %><div><dt>Bedrooms</dt><dd>$Bedrooms</dd></div><% end_if %>
                            <% if $Bathrooms %><div><dt>Bathrooms</dt><dd>$Bathrooms</dd></div><% end_if %>
                        </dl>
                    <% end_if %>
                <% end_with %>

                <% with $PropertyEnquiryLink %>
                    <% if $exists %>
                        <a class="reapit-property-detail__enquiry-link" href="$URL.ATT"<% if $OpenInNew %> target="_blank" rel="noopener noreferrer"<% end_if %>>$LinkText.XML</a>
                    <% end_if %>
                <% end_with %>
            </div>

            <% with $Property %>
                <% if $Address %>
                    <section class="reapit-property-detail__panel" aria-labelledby="reapit-property-location">
                        <h2 id="reapit-property-location">Location</h2>
                        <p>$Address.XML<% if $Postcode %><span class="reapit-property-detail__postcode">$Postcode.XML</span><% end_if %></p>
                    </section>
                <% end_if %>
            <% end_with %>
        </aside>
    </div>
</div>
