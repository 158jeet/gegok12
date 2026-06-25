<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>{{ $school->name }} - Visitor Pass</title>
</head>
<body style="margin:0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial; background:#f6f7f8;">
  <!-- Centering container -->
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="min-height:100vh; border-collapse:collapse;">
    <tr>
      <td align="center" valign="middle" style="padding: 24px;">
        <!-- Card -->
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" >
          <!-- Card padding -->
          <tr>
            <td style="padding:40px 56px;">
              <!-- Header: use table to place title at left and date/button at right -->
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse; margin-bottom:16px;">
                <tr>
                  <td valign="middle" style="vertical-align:middle;">
                    <div style="font-size:22px; font-weight:700; line-height:1; color:#111;">Visitor Pass</div>
                  </td>
                  <td valign="middle" align="right" style="vertical-align:middle; width:340px;">
                    <div style="font-size:20px; font-weight:500; color:#111; margin-bottom:12px;">
                        {{ \Carbon\Carbon::parse($visitorlog->date_of_visit . ' ' . $visitorlog->entry_time)
                            ->format('d-M-Y h:i A') }}
                    </div>
                    <!-- Optional button visual (remove if not required in PDF) -->
                   
                  </td>
                </tr>
              </table>
              <!-- Thin divider -->
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse; margin-bottom:22px;">
                <tr>
                  <td style="height:1px; background:rgba(0,0,0,0.06);"></td>
                </tr>
              </table>
              <!-- Main details as two-column table: label left, value right -->
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse; font-size:20px; color:#111;">
                <!-- Visiting Purpose row (spans full width with label and value) -->
                <tr>
                  <td style="padding:18px 0; width:34%; font-weight:700; vertical-align:top;">Visiting Purpose</td>
                  <td style="padding:18px 0; vertical-align:top;">{{ ucwords(str_replace('_',' ',$visitorlog->visiting_purpose)) }}</td>
                </tr>
                <!-- Small gap / heading for visitor details -->
                <tr>
                  <td colspan="2" style="font-size:22px; font-weight:700; line-height:1; color:#111;">Visitor Details</td>
                </tr>
                <!-- Name -->
                <tr>
                  <td style="padding:16px 0; width:34%; font-weight:700; vertical-align:top;">Name</td>
                  <td style="padding:16px 0; vertical-align:top;">{{ ucwords($visitorlog->name) }}</td>
                </tr>
                <!-- Company Name -->
                <tr>
                  <td style="padding:16px 0; width:34%; font-weight:700; vertical-align:top;">Relation With Student :</td>
                  <td style="padding:16px 0; vertical-align:top;">{{ ucfirst($visitorlog->relation_with_student) }}</td>
                </tr>
                <!-- Contact Number -->
                <tr>
                  <td style="padding:16px 0; width:34%; font-weight:700; vertical-align:top;">Student Name :</td>
                  <td style="padding:16px 0; vertical-align:top;">{{ $visitorlog->student->FullName }}</td>
                </tr>
                
                <!-- Whom to Meet (show name + designation on new line) -->
                <tr>
                  <td style="padding:16px 0; width:34%; font-weight:700; vertical-align:top;">Whom to Meet</td>
                  <td style="padding:16px 0; vertical-align:top;">
                    {{ $visitorlog->employee->FullName }}<br />
                    <span style="font-size:18px; color:rgba(0,0,0,0.7);">({{ ucwords(str_replace('_',' ',$visitorlog->employee->teacherprofile[0]->designation)) }})</span>
                  </td>
                </tr>
                <!-- Visitor Count -->
                <tr>
                  <td style="padding:16px 0; width:34%; font-weight:700; vertical-align:top;">Visitor Count</td>
                  <td style="padding:16px 0; vertical-align:top;">{{ $visitorlog->number_of_visitors }}</td>
                </tr>
              </table>
              <!-- Add subtle separators between rows by adding bottom borders using an extra table overlay -->
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse; margin-top:8px;">
                <tr>
                  <td style="height:1px; background:transparent;"></td>
                </tr>
              </table>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>