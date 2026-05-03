@component('mail::message')
<!-- ── HEADER BASED ON ROLE ── -->
@if(in_array($user->role, ['hr', 'hr_intern', 'superadmin']))
# Welcome to the CLIMBS HR Portal
@else
# Welcome to the CLIMBS Internship Program
@endif

Hello **{{ $user->first_name ?? 'there' }}**,

<!-- ── MESSAGE BASED ON ROLE ── -->
@if(in_array($user->role, ['hr', 'hr_intern', 'superadmin']))
Your administrative account has been successfully created. Please verify your email address to access the management dashboard.
@else
We are thrilled to have you on board. Please verify your email address so you can log in and start tracking your hours.
@endif

@component('mail::button', ['url' => $url, 'color' => 'primary'])
Verify My Account
@endcomponent

If you did not request this account, no further action is required.

Thanks,<br>
**CLIMBS InternTracker Team**

<br><br>

<!-- ✨ THE FIX: NO INDENTATION ALLOWED HERE ✨ -->
<div style="background-color: #0047AB; border-top: 10px solid #F2A71B; padding: 25px 20px; font-family: 'Poppins', Arial, sans-serif; color: #ffffff; border-radius: 0 0 8px 8px;">
<table width="100%" cellpadding="0" cellspacing="0" border="0">
<tr>
<!-- Left Side: Logo Box -->
<td width="130" valign="center" style="padding-right: 20px;">
<div style="border: 2px solid #F2A71B; padding: 10px; background-color: #0047AB; text-align: center;">
<img src="https://www.climbs.coop/wp-content/uploads/2023/07/CLIMBS-Life-and-General-Insurance-Cooperative.png" alt="CLIMBS Logo" width="100">
</div>
</td>
<!-- Right Side: Contact Info -->
<td valign="center" style="line-height: 1.6; font-size: 13px;">
<h3 style="color: #F2A71B; margin: 0 0 8px 0; font-size: 15px; text-transform: uppercase;">CLIMBS Life and General Insurance Cooperative</h3>
<p style="margin: 0 0 4px 0;"><strong style="color: #ffffff;">Contact No.:</strong> (+63) 917 701 6903</p>
<p style="margin: 0 0 4px 0;"><strong style="color: #ffffff;">Address:</strong> Zone 5, National Highway Bulua, Cagayan de Oro City, 9000 PH</p>
<p style="margin: 0 0 4px 0;"><strong style="color: #ffffff;">Website:</strong> <a href="https://www.climbs.coop" style="color: #ffffff; text-decoration: none;">https://www.climbs.coop</a></p>
<p style="margin: 0 0 12px 0;"><strong style="color: #ffffff;">Email:</strong> <a href="mailto:hrad.assistant@climbs.coop" style="color: #ffffff; text-decoration: none;">hrad.assistant@climbs.coop</a></p>
<p style="color: #F2A71B; margin: 0; font-weight: bold; font-style: italic;">“A Climate Insurance: Insuring Where You Are”</p>
</td>
</tr>
</table>
</div>
@endcomponent