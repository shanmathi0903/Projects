package beanFolder;

import java.util.ArrayList;

public class Booking{
	private String customerName;
	private long phoneNumber;
	private String busName;
	private String regNo;
	private ArrayList<Integer> seaterSeats;
	private ArrayList<Integer> sleeperSeats;
	private String source;
	private String destination;
	private String date;

	public Booking(String customerName,long phoneNumber,String busName,String regNo,ArrayList<Integer> seaterSeats,ArrayList<Integer> sleeperSeats,String source,String destination,String date){
		this.customerName=customerName;
		this.phoneNumber=phoneNumber;
		this.busName=busName;
		this.regNo=regNo;
		this.seaterSeats=seaterSeats;
		this.sleeperSeats=sleeperSeats;
		this.source=source;
		this.destination=destination;
		this.date=date;
	}

	public String getCustomerName(){
		return customerName;
	}
	public long getPhoneNumber(){
		return phoneNumber;
	}
	public String getBusName(){
		return busName;
	}
	public String getRegNo(){
		return regNo;
	}
	public ArrayList<Integer> getSeaterSeats(){
		return seaterSeats;
	}
	public ArrayList<Integer> getSleeperSeats(){
		return sleeperSeats;
	}
	public String getSource(){
		return source;
	}
	public String getDestination(){
		return destination;
	}
	public String getDate(){
		return date;
	}
}
